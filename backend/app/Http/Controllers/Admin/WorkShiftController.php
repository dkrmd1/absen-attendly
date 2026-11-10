<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkShiftController extends Controller
{
    public function index(): View
    {
        $shifts = WorkShift::query()
            ->withCount(['employees as employees_count' => fn ($query) => $query->staff()])
            ->orderBy('name')
            ->get();

        return view('admin.shifts.index', compact('shifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        WorkShift::query()->create($this->validatedAttributes($request));

        return redirect()->route('admin.shifts.index')->with('success', 'Shift berhasil ditambahkan.');
    }

    public function update(Request $request, WorkShift $workShift): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request, $workShift);

        if (! $attributes['is_active'] && $workShift->is_active
            && $workShift->employees()->staff()->exists()) {
            throw ValidationException::withMessages([
                'is_active' => 'Pindahkan karyawan dari shift ini sebelum menonaktifkannya.',
            ]);
        }

        $workShift->update($attributes);

        return redirect()->route('admin.shifts.index')->with('success', 'Shift berhasil diperbarui.');
    }

    /**
     * @return array{name: string, start_time: string, end_time: string, grace_period_minutes: int, work_days: list<int>, is_active: bool}
     */
    private function validatedAttributes(Request $request, ?WorkShift $workShift = null): array
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('work_shifts', 'name')->ignore($workShift?->id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_period_minutes' => ['required', 'integer', 'between:0,120'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($attributes['start_time'] === $attributes['end_time']) {
            throw ValidationException::withMessages([
                'end_time' => 'Jam selesai harus berbeda dari jam mulai.',
            ]);
        }

        $attributes['work_days'] = array_values(array_map('intval', $attributes['work_days']));
        sort($attributes['work_days']);

        return $attributes;
    }
}
