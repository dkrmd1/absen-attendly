<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use App\Models\WorkShift;
use App\Notifications\AttendanceWorkflowNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceNotificationService
{
    private const NO_SHOW_BUFFER_MINUTES = 15;

    public function notifyLateCheckIn(Attendance $attendance): void
    {
        $lateMinutes = (int) $attendance->late_minutes;
        if ($lateMinutes < 1) {
            return;
        }

        $employee = $attendance->user()->with(['workGroup.leader', 'department.supervisor', 'department.director'])->first();
        if (! $employee) {
            return;
        }

        $shiftName = $attendance->shift_name ?: 'jadwal kerja';
        foreach ($this->recipientsFor($employee) as $recipient) {
            $recipient->notify(new AttendanceWorkflowNotification(
                'Check-in terlambat',
                "{$employee->name} check-in terlambat {$lateMinutes} menit pada {$shiftName}.",
                [
                    'kind' => 'attendance_late_check_in',
                    'attendance_id' => $attendance->id,
                    'work_date' => $attendance->work_date->toDateString(),
                ],
            ));
        }
    }

    public function notifyMissingCheckIns(): int
    {
        $now = now();
        $workDate = $now->toDateString();
        $weekday = $now->dayOfWeekIso;

        $employees = User::query()
            ->staff()
            ->where('is_active', true)
            ->whereHas('workShift', fn ($query) => $query->where('is_active', true))
            ->whereDoesntHave('attendances', fn ($query) => $query->whereDate('work_date', $workDate))
            ->whereDoesntHave('leaveRequests', fn ($query) => $query
                ->where('status', 'approved')
                ->whereIn('type', ['izin', 'sakit', 'cuti'])
                ->whereDate('start_date', '<=', $workDate)
                ->whereDate('end_date', '>=', $workDate))
            ->with(['workShift', 'workGroup.leader', 'department.supervisor', 'department.director'])
            ->get();

        $digests = [];
        foreach ($employees as $employee) {
            $shift = $employee->workShift;
            if (! $shift || ! $this->isScheduledToday($shift, $weekday)) {
                continue;
            }

            $cutoff = Carbon::parse(
                "{$workDate} {$shift->start_time}",
                config('app.timezone'),
            )->addMinutes($shift->grace_period_minutes + self::NO_SHOW_BUFFER_MINUTES);
            if ($now->lt($cutoff)) {
                continue;
            }

            foreach ($this->recipientsFor($employee) as $recipient) {
                $key = "{$recipient->id}:{$shift->id}";
                $digests[$key] ??= [
                    'recipient' => $recipient,
                    'shift' => $shift,
                    'employees' => [],
                ];
                $digests[$key]['employees'][] = $employee;
            }
        }

        $sent = 0;
        foreach ($digests as $digest) {
            /** @var User $recipient */
            $recipient = $digest['recipient'];
            /** @var WorkShift $shift */
            $shift = $digest['shift'];

            $alreadySent = $recipient->notifications()
                ->where('type', AttendanceWorkflowNotification::class)
                ->where('data->data->kind', 'attendance_missing_check_in')
                ->where('data->data->work_date', $workDate)
                ->where('data->data->work_shift_id', $shift->id)
                ->exists();
            if ($alreadySent) {
                continue;
            }

            $employeeIds = array_map(
                static fn (User $employee): int => $employee->id,
                $digest['employees'],
            );
            $count = count($employeeIds);
            $recipient->notify(new AttendanceWorkflowNotification(
                'Ringkasan belum check-in',
                "{$count} karyawan belum check-in untuk shift {$shift->name} setelah batas toleransi.",
                [
                    'kind' => 'attendance_missing_check_in',
                    'work_date' => $workDate,
                    'work_shift_id' => $shift->id,
                    'employee_ids' => $employeeIds,
                ],
            ));
            $sent++;
        }

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(User $employee): Collection
    {
        $group = $employee->workGroup;
        if ($group?->is_active && $group->leader?->is_active && $group->leader_id !== $employee->id) {
            return collect([$group->leader]);
        }

        $department = $employee->department;
        if ($department?->is_active) {
            if ($department->supervisor?->is_active && $department->supervisor_id !== $employee->id) {
                return collect([$department->supervisor]);
            }
            if ($department->director?->is_active && $department->director_id !== $employee->id) {
                return collect([$department->director]);
            }
        }

        $hrUsers = User::query()->where('role', 'hr')->where('is_active', true)->get();
        if ($hrUsers->isNotEmpty()) {
            return $hrUsers;
        }

        return User::query()->where('role', 'admin')->where('is_active', true)->get();
    }

    private function isScheduledToday(WorkShift $shift, int $weekday): bool
    {
        return in_array($weekday, $shift->work_days ?? [], true);
    }
}
