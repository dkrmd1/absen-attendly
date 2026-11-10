<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkGroup;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(): View
    {
        $employees = User::query()
            ->where('role', '!=', 'admin')
            ->with([
                'baseLocation:id,name',
                'department:id,name',
                'supervisedDepartment:id,name,supervisor_id',
                'workGroup:id,name',
                'workShift:id,name,start_time,end_time',
                'directedDepartments:id,director_id',
            ])
            ->withCount('attendances')
            ->latest()
            ->paginate(15);
        $locations = WorkLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $groups = WorkGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id']);
        $shifts = WorkShift::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.employees.index', compact('employees', 'locations', 'departments', 'groups', 'shifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $role = $request->input('role', 'employee');
        $requiresWorkAssignment = ! in_array($role, ['ceo', 'commissioner'], true);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'department_id' => [
                Rule::requiredIf($requiresWorkAssignment && Department::query()->where('is_active', true)->exists()),
                'nullable',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'base_location_id' => [
                Rule::requiredIf($requiresWorkAssignment && WorkLocation::query()->where('is_active', true)->exists()),
                'nullable',
                Rule::exists('work_locations', 'id')->where('is_active', true),
            ],
            'work_shift_id' => [
                'nullable',
                Rule::exists('work_shifts', 'id')->where('is_active', true),
            ],
            'work_group_id' => [
                'nullable',
                Rule::exists('work_groups', 'id')->where('is_active', true)
                    ->where('department_id', $request->input('department_id')),
            ],
            'role' => [
                'nullable',
                Rule::in(['employee', 'hr', 'director', 'ceo', 'commissioner']),
                Rule::unique('users', 'role')
                    ->where(fn ($query) => $query->where('role', 'ceo')),
            ],
        ]);
        $attributes['role'] = $attributes['role'] ?? 'employee';
        if (in_array($attributes['role'], ['ceo', 'commissioner'], true)) {
            $attributes['department_id'] = null;
            $attributes['base_location_id'] = null;
            $attributes['work_shift_id'] = null;
        }
        $attributes['password'] = Hash::make($attributes['password']);

        User::query()->create($attributes);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        abort_unless(! $employee->isAdmin(), 404);

        $canChangeRole = in_array($employee->role, ['employee', 'hr', 'director', 'ceo', 'commissioner'], true)
            && ! $employee->supervisedDepartment()->exists()
            && ! $employee->directedDepartments()->exists();
        $canEditStructure = $canChangeRole
            || ($employee->role === 'employee' && ! $employee->supervisedDepartment()->exists());

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->id)],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ];
        if ($canChangeRole) {
            $rules['role'] = [
                'sometimes',
                'required',
                Rule::in(['employee', 'hr', 'director', 'ceo', 'commissioner']),
                Rule::unique('users', 'role')
                    ->where(fn ($query) => $query->where('role', 'ceo'))
                    ->ignore($employee->id),
            ];
        }

        if ($canEditStructure) {
            $rules['department_id'] = [
                'sometimes',
                Rule::requiredIf(
                    ($request->input('role', $employee->role) === 'employee')
                    && Department::query()->where('is_active', true)->exists(),
                ),
                'nullable',
                Rule::exists('departments', 'id')->where('is_active', true),
            ];
            $rules['work_group_id'] = [
                'sometimes',
                'nullable',
                Rule::exists('work_groups', 'id')->where('is_active', true)
                    ->where('department_id', $request->input('department_id', $employee->department_id)),
            ];
        }

        $requiresWorkAssignment = ! in_array($request->input('role', $employee->role), ['ceo', 'commissioner'], true);
        $rules['base_location_id'] = [
            'sometimes',
            Rule::requiredIf(
                $requiresWorkAssignment && WorkLocation::query()->where('is_active', true)->exists(),
            ),
            'nullable',
            Rule::exists('work_locations', 'id')->where('is_active', true),
        ];
        $rules['work_shift_id'] = [
            'sometimes',
            'nullable',
            Rule::exists('work_shifts', 'id')->where('is_active', true),
        ];

        $attributes = $request->validate($rules, [
            'password.min' => 'Kata sandi baru minimal 12 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);
        $role = $attributes['role'] ?? $employee->role;
        $updates = [
            'name' => $attributes['name'],
            'email' => $attributes['email'],
        ];

        $roleWasSubmitted = array_key_exists('role', $attributes);
        if ($roleWasSubmitted) {
            $updates['role'] = $role;
        }

        if ($roleWasSubmitted && in_array($role, ['ceo', 'commissioner'], true)) {
            $updates += [
                'department_id' => null,
                'work_group_id' => null,
                'base_location_id' => null,
                'work_shift_id' => null,
            ];
        } else {
            if ($canEditStructure && array_key_exists('department_id', $attributes)) {
                $updates['department_id'] = $attributes['department_id'];
            }
            if ($canEditStructure && array_key_exists('work_group_id', $attributes)) {
                $updates['work_group_id'] = $role === 'employee' ? $attributes['work_group_id'] : null;
            } elseif ($roleWasSubmitted && $role !== 'employee') {
                $updates['work_group_id'] = null;
            }

            if (array_key_exists('base_location_id', $attributes)) {
                $updates['base_location_id'] = $attributes['base_location_id'];
            }
            if (array_key_exists('work_shift_id', $attributes)) {
                $updates['work_shift_id'] = $attributes['work_shift_id'];
            }
        }

        $passwordChanged = ! empty($attributes['password']);
        if ($passwordChanged) {
            $updates['password'] = $attributes['password'];
        }

        DB::transaction(function () use ($employee, $updates, $passwordChanged): void {
            $employee->update($updates);
            if ($passwordChanged) {
                $employee->tokens()->delete();
                if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $employee->id)->delete();
                }
            }
        });

        return redirect()->route('admin.employees.index')
            ->with('success', $passwordChanged
                ? 'Data karyawan berhasil diperbarui dan sesi akun lama telah dikeluarkan.'
                : 'Data karyawan berhasil diperbarui.');
    }

    public function archive(Request $request, User $employee): RedirectResponse
    {
        abort_unless(! $employee->isAdmin() && $employee->is_active, 404);
        if ($employee->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'employee' => 'Akun yang sedang digunakan tidak dapat diarsipkan.',
            ]);
        }
        if ($employee->supervisedDepartment()->exists() || $employee->ledWorkGroup()->exists()) {
            throw ValidationException::withMessages([
                'employee' => 'Alihkan tugas supervisor atau ketua group sebelum mengarsipkan karyawan ini.',
            ]);
        }

        $employee->update(['is_active' => false]);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Akun karyawan berhasil diarsipkan.');
    }

    public function restore(User $employee): RedirectResponse
    {
        abort_unless(! $employee->isAdmin() && ! $employee->is_active, 404);
        $employee->update(['is_active' => true]);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Akun karyawan berhasil diaktifkan kembali.');
    }

    public function updateLocation(Request $request, User $employee): RedirectResponse
    {
        abort_unless(! $employee->isAdmin(), 404);

        $attributes = $request->validate([
            'base_location_id' => [
                'required',
                Rule::exists('work_locations', 'id')->where('is_active', true),
            ],
        ]);

        $employee->update($attributes);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Cabang utama karyawan berhasil diperbarui.');
    }

    public function updateDepartment(Request $request, User $employee): RedirectResponse
    {
        abort_unless(
            $employee->role === 'employee' && ! $employee->supervisedDepartment()->exists(),
            404,
        );

        $attributes = $request->validate([
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
        ]);

        $employee->update([
            ...$attributes,
            'work_group_id' => null,
        ]);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Departemen karyawan berhasil diperbarui.');
    }

    public function updateShift(Request $request, User $employee): RedirectResponse
    {
        abort_unless(! $employee->isAdmin(), 404);

        $attributes = $request->validate([
            'work_shift_id' => [
                'nullable',
                Rule::exists('work_shifts', 'id')->where('is_active', true),
            ],
        ]);

        $employee->update($attributes);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Shift karyawan berhasil diperbarui.');
    }

    public function updateRole(Request $request, User $employee): RedirectResponse
    {
        abort_unless($employee->role !== 'admin', 404);
        if (
            in_array($employee->role, ['group_lead', 'division_head'], true)
            || $employee->supervisedDepartment()->exists()
        ) {
            throw ValidationException::withMessages([
                'role' => 'Peran ketua group dan kepala divisi dikelola dari menu struktur organisasi.',
            ]);
        }
        if ($employee->directedDepartments()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Alihkan penugasan direktur dari departemen sebelum mengubah peran ini.',
            ]);
        }

        $attributes = $request->validate([
            'role' => [
                'required',
                Rule::in(['employee', 'hr', 'director', 'ceo', 'commissioner']),
                Rule::unique('users', 'role')
                    ->where(fn ($query) => $query->where('role', 'ceo'))
                    ->ignore($employee->id),
            ],
        ]);
        $employee->update([
            ...$attributes,
            'work_group_id' => $attributes['role'] === 'employee' ? $employee->work_group_id : null,
            ...in_array($attributes['role'], ['ceo', 'commissioner'], true)
                ? ['department_id' => null, 'base_location_id' => null, 'work_shift_id' => null]
                : [],
        ]);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Peran akun berhasil diperbarui.');
    }

    public function updateGroup(Request $request, User $employee): RedirectResponse
    {
        abort_unless(
            $employee->role === 'employee' && ! $employee->supervisedDepartment()->exists(),
            404,
        );
        $attributes = $request->validate([
            'work_group_id' => [
                'nullable',
                Rule::exists('work_groups', 'id')->where('is_active', true)
                    ->where('department_id', $employee->department_id),
            ],
        ]);
        $employee->update($attributes);

        return redirect()->route('admin.employees.index')
            ->with('success', 'Penempatan group berhasil diperbarui.');
    }
}
