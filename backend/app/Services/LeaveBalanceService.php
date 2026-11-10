<?php

namespace App\Services;

use App\Models\CompanyHoliday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class LeaveBalanceService
{
    public function summary(User $employee, ?int $year = null): array
    {
        $year ??= now()->year;
        $balance = LeaveBalance::query()
            ->where('user_id', $employee->id)
            ->where('year', $year)
            ->first();

        if (! $balance) {
            return [
                'configured' => false,
                'year' => $year,
                'entitlement_days' => null,
                'used_days' => null,
                'remaining_days' => null,
            ];
        }

        $usedDays = $this->usedDays($employee, $year);

        return [
            'configured' => true,
            'year' => $year,
            'entitlement_days' => $balance->entitlement_days,
            'used_days' => $usedDays,
            'remaining_days' => $balance->entitlement_days - $usedDays,
        ];
    }

    public function assertAvailable(User $employee, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $years = range($start->year, $end->year);
        $balances = LeaveBalance::query()
            ->where('user_id', $employee->id)
            ->whereIn('year', $years)
            ->orderBy('year')
            ->lockForUpdate()
            ->get()
            ->keyBy('year');

        foreach ($years as $year) {
            $balance = $balances->get($year);
            if (! $balance) {
                throw ValidationException::withMessages([
                    'status' => "Saldo cuti tahunan karyawan belum diatur HRD untuk tahun {$year}.",
                ]);
            }

            $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
            $yearEnd = CarbonImmutable::create($year, 12, 31)->endOfDay();
            $periodStart = $start->greaterThan($yearStart) ? $start : $yearStart;
            $periodEnd = $end->lessThan($yearEnd) ? $end : $yearEnd;
            $requestedDays = $this->workingDays($employee, $periodStart, $periodEnd);

            if ($requestedDays === 0) {
                continue;
            }

            $remainingDays = $balance->entitlement_days - $this->usedDays($employee, $year);
            if ($requestedDays > $remainingDays) {
                throw ValidationException::withMessages([
                    'status' => "Saldo cuti tahunan tahun {$year} tidak cukup. Tersedia {$remainingDays} hari, diajukan {$requestedDays} hari kerja.",
                ]);
            }
        }
    }

    public function usedDays(User $employee, int $year): int
    {
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->endOfDay();
        $requests = LeaveRequest::query()
            ->where('user_id', $employee->id)
            ->where('type', 'cuti')
            ->where('subtype', 'cuti_tahunan')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $yearEnd->toDateString())
            ->whereDate('end_date', '>=', $yearStart->toDateString())
            ->get(['start_date', 'end_date']);

        return $requests->sum(function (LeaveRequest $request) use ($employee, $yearStart, $yearEnd) {
            $requestStart = CarbonImmutable::parse($request->start_date)->startOfDay();
            $requestEnd = CarbonImmutable::parse($request->end_date)->endOfDay();
            $periodStart = $requestStart->greaterThan($yearStart) ? $requestStart : $yearStart;
            $periodEnd = $requestEnd->lessThan($yearEnd) ? $requestEnd : $yearEnd;

            return $this->workingDays($employee, $periodStart, $periodEnd);
        });
    }

    private function workingDays(User $employee, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $employee->loadMissing('workShift');
        $workDays = $employee->workShift?->work_days;
        $workDays = is_array($workDays) && $workDays !== []
            ? $workDays
            : [1, 2, 3, 4, 5];
        $holidays = CompanyHoliday::query()
            ->where('is_active', true)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->flip();

        $days = 0;
        for ($date = $start->startOfDay(); ! $date->greaterThan($end->startOfDay()); $date = $date->addDay()) {
            if (in_array($date->dayOfWeekIso, $workDays, true) && ! $holidays->has($date->toDateString())) {
                $days++;
            }
        }

        return $days;
    }
}
