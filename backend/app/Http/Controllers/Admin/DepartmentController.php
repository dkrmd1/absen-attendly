<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::query()
            ->with(['supervisor:id,name,email', 'director:id,name,email'])
            ->withCount(['employees as employees_count' => fn ($query) => $query->staff()])
            ->orderBy('name')
            ->get();
        $supervisorsByDepartment = $departments->mapWithKeys(fn (Department $department) => [
            $department->id => User::query()
                ->whereIn('role', ['employee', 'division_head'])
                ->where('department_id', $department->id)
                ->whereDoesntHave('supervisedDepartment', fn ($query) => $query
                    ->where('id', '!=', $department->id))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
        $directors = User::query()->where('role', 'director')->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.departments.index', compact('departments', 'supervisorsByDepartment', 'directors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request);
        $attributes['supervisor_id'] = $this->supervisorId($request);
        $attributes['director_id'] = $this->directorId($request);
        Department::query()->create($attributes);

        return redirect()->route('admin.departments.index')->with('success', 'Departemen berhasil ditambahkan.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $attributes = $this->validatedAttributes($request, $department);
        $attributes['supervisor_id'] = $this->supervisorId($request, $department);
        $attributes['director_id'] = $this->directorId($request);

        if (! $attributes['is_active'] && $department->is_active) {
            $hasEmployees = $department->employees()->staff()->exists();
            $hasActiveGroups = $department->workGroups()->where('is_active', true)->exists();

            if ($hasEmployees || $hasActiveGroups) {
                throw ValidationException::withMessages([
                    'is_active' => 'Pindahkan karyawan dan nonaktifkan group sebelum menonaktifkan departemen ini.',
                ]);
            }
        }

        $previousSupervisor = $department->supervisor;
        $nextSupervisor = isset($attributes['supervisor_id'])
            ? User::query()->find($attributes['supervisor_id'])
            : null;

        DB::transaction(function () use ($attributes, $department, $nextSupervisor, $previousSupervisor) {
            $department->update($attributes);
            if (
                $previousSupervisor
                && $previousSupervisor->id !== $nextSupervisor?->id
                && $previousSupervisor->role === 'division_head'
            ) {
                $previousSupervisor->update(['role' => 'employee']);
            }
            if ($nextSupervisor) {
                $nextSupervisor->update([
                    'role' => 'division_head',
                    'work_group_id' => null,
                ]);
            }
        });

        return redirect()->route('admin.departments.index')->with('success', 'Departemen berhasil diperbarui.');
    }

    private function supervisorId(Request $request, Department $department = null): ?int
    {
        return $request->validate([
            'supervisor_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($department) {
                    $query->whereIn('role', ['employee', 'division_head']);
                    if ($department) {
                        $query->where('department_id', $department->id);
                    }
                }),
                Rule::unique('departments', 'supervisor_id')->ignore($department?->id),
            ],
        ])['supervisor_id'] ?? null;
    }

    private function directorId(Request $request): ?int
    {
        return $request->validate([
            'director_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'director')
                    ->where('is_active', true)),
            ],
        ])['director_id'] ?? null;
    }

    /**
     * @return array{name: string, description: ?string, is_active: bool}
     */
    private function validatedAttributes(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
