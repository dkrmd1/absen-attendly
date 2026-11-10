<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRecordController extends Controller
{
    public function index(): View
    {
        return view('admin.payroll.index', [
            'employees' => User::query()->staff()->orderBy('name')->get(['id', 'name', 'email']),
            'records' => PayrollRecord::query()->with('user:id,name,email')->latest('period_start')->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'gross_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'deductions_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'payslip' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        $employee = User::query()->staff()->whereKey($attributes['user_id'])->first();
        if (! $employee) {
            throw ValidationException::withMessages(['user_id' => 'Pilih akun karyawan yang valid.']);
        }
        $gross = (float) $attributes['gross_amount'];
        $deductions = (float) $attributes['deductions_amount'];
        if ($deductions > $gross) {
            throw ValidationException::withMessages(['deductions_amount' => 'Potongan tidak boleh melebihi gaji bruto.']);
        }

        $record = PayrollRecord::query()
            ->where('user_id', $employee->id)
            ->whereDate('period_start', $attributes['period_start'])
            ->first();
        if ($record?->archived_at) {
            throw ValidationException::withMessages([
                'period_start' => 'Data gaji untuk periode ini sudah diarsipkan. Pulihkan data tersebut sebelum mengubahnya.',
            ]);
        }
        $newPath = $request->file('payslip')?->store('private/payslips', 'local');
        if ($request->hasFile('payslip') && ! $newPath) {
            throw new \RuntimeException('Slip gaji gagal disimpan ke penyimpanan privat.');
        }
        $oldPath = $record?->payslip_path;

        try {
            DB::transaction(function () use ($record, $employee, $attributes, $gross, $deductions, $newPath, $request) {
                $values = [
                    'period_start' => $attributes['period_start'],
                    'gross_amount' => $gross,
                    'deductions_amount' => $deductions,
                    'net_amount' => $gross - $deductions,
                    'published_by' => $request->user()->id,
                ];
                if ($newPath) {
                    $values['payslip_path'] = $newPath;
                }

                if ($record) {
                    $record->update($values);
                } else {
                    PayrollRecord::query()->create([
                        ...$values,
                        'user_id' => $employee->id,
                    ]);
                }
            });
        } catch (\Throwable $error) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }

            throw $error;
        }

        if ($oldPath && $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('admin.payroll.index')
            ->with('success', 'Data gaji berhasil disimpan dan hanya dapat dilihat karyawan terkait.');
    }

    public function update(Request $request, PayrollRecord $payrollRecord): RedirectResponse
    {
        abort_if($payrollRecord->archived_at, 404);
        $attributes = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'gross_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'deductions_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'payslip' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        $employee = User::query()->staff()->whereKey($attributes['user_id'])->first();
        if (! $employee) {
            throw ValidationException::withMessages(['user_id' => 'Pilih akun karyawan yang valid.']);
        }

        $duplicate = PayrollRecord::query()
            ->where('user_id', $employee->id)
            ->whereDate('period_start', $attributes['period_start'])
            ->whereKeyNot($payrollRecord->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'period_start' => 'Data gaji untuk karyawan dan periode tersebut sudah tersedia.',
            ]);
        }

        $gross = (float) $attributes['gross_amount'];
        $deductions = (float) $attributes['deductions_amount'];
        if ($deductions > $gross) {
            throw ValidationException::withMessages(['deductions_amount' => 'Potongan tidak boleh melebihi gaji bruto.']);
        }

        $newPath = $request->file('payslip')?->store('private/payslips', 'local');
        if ($request->hasFile('payslip') && ! $newPath) {
            throw new \RuntimeException('Slip gaji gagal disimpan ke penyimpanan privat.');
        }
        $oldPath = $payrollRecord->payslip_path;

        try {
            DB::transaction(function () use ($payrollRecord, $employee, $attributes, $gross, $deductions, $newPath, $request) {
                $payrollRecord->update([
                    'user_id' => $employee->id,
                    'period_start' => $attributes['period_start'],
                    'gross_amount' => $gross,
                    'deductions_amount' => $deductions,
                    'net_amount' => $gross - $deductions,
                    'published_by' => $request->user()->id,
                    ...($newPath ? ['payslip_path' => $newPath] : []),
                ]);
            });
        } catch (\Throwable $error) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }

            throw $error;
        }

        if ($newPath && $newPath !== $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('admin.payroll.index')
            ->with('success', 'Data gaji berhasil diperbarui.');
    }

    public function payslip(PayrollRecord $payrollRecord): StreamedResponse
    {
        abort_unless($payrollRecord->payslip_path && ! $payrollRecord->archived_at, 404);

        return Storage::disk('local')->download($payrollRecord->payslip_path);
    }

    public function archive(PayrollRecord $payrollRecord): RedirectResponse
    {
        $payrollRecord->update(['archived_at' => now()]);

        return redirect()->route('admin.payroll.index')
            ->with('success', 'Data gaji berhasil diarsipkan.');
    }

    public function restore(PayrollRecord $payrollRecord): RedirectResponse
    {
        $payrollRecord->update(['archived_at' => null]);

        return redirect()->route('admin.payroll.index')
            ->with('success', 'Data gaji berhasil dipulihkan.');
    }
}
