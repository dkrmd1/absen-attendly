<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function index(Request $request, LeaveBalanceService $leaveBalances): View
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $year = (int) ($validated['year'] ?? now()->year);
        $employees = User::query()
            ->staff()
            ->with('workShift:id,name,work_days')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();
        $balances = LeaveBalance::query()
            ->where('year', $year)
            ->whereIn('user_id', $employees->getCollection()->pluck('id'))
            ->get()
            ->keyBy('user_id');
        $summaries = $employees->getCollection()
            ->mapWithKeys(fn (User $employee) => [
                $employee->id => $leaveBalances->summary($employee, $year),
            ]);
        $holidays = CompanyHoliday::query()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        return view('admin.leave-balances.index', compact(
            'employees',
            'balances',
            'summaries',
            'holidays',
            'year',
        ));
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        abort_unless(! $employee->isAdmin(), 404);
        $attributes = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'entitlement_days' => ['required', 'integer', 'between:0,3650'],
        ]);

        LeaveBalance::query()->updateOrCreate(
            [
                'user_id' => $employee->id,
                'year' => $attributes['year'],
            ],
            [
                'entitlement_days' => $attributes['entitlement_days'],
                'updated_by' => $request->user()->id,
            ],
        );

        return redirect()->route('admin.leave-balances.index', [
            'year' => $attributes['year'],
        ])->with('success', 'Jatah cuti tahunan karyawan berhasil disimpan.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'unique:company_holidays,date'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        CompanyHoliday::query()->create([
            ...$attributes,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.leave-balances.index', [
            'year' => (int) substr($attributes['date'], 0, 4),
        ])->with('success', 'Hari libur perusahaan berhasil ditambahkan.');
    }

    public function updateHoliday(Request $request, CompanyHoliday $companyHoliday): RedirectResponse
    {
        abort_unless($companyHoliday->is_active, 404);
        $attributes = $request->validate([
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('company_holidays', 'date')->ignore($companyHoliday->id),
            ],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $companyHoliday->update($attributes);

        return redirect()->route('admin.leave-balances.index', [
            'year' => (int) substr($attributes['date'], 0, 4),
        ])->with('success', 'Hari libur perusahaan berhasil diperbarui.');
    }

    public function destroyHoliday(CompanyHoliday $companyHoliday): RedirectResponse
    {
        $year = (int) $companyHoliday->date->format('Y');
        $companyHoliday->update(['is_active' => false]);

        return redirect()->route('admin.leave-balances.index', [
            'year' => $year,
        ])->with('success', 'Hari libur perusahaan berhasil diarsipkan.');
    }

    public function restoreHoliday(CompanyHoliday $companyHoliday): RedirectResponse
    {
        $companyHoliday->update(['is_active' => true]);
        $year = (int) $companyHoliday->date->format('Y');

        return redirect()->route('admin.leave-balances.index', [
            'year' => $year,
        ])->with('success', 'Hari libur perusahaan berhasil diaktifkan kembali.');
    }
}
