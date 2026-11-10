<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkGroupController extends Controller
{
    public function index(): View
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $groups = WorkGroup::query()
            ->with(['department:id,name', 'leader:id,name,email'])
            ->withCount(['employees as employees_count' => fn ($query) => $query
                ->whereIn('role', ['employee', 'group_lead'])])
            ->orderBy('name')
            ->get();
        $leaders = User::query()
            ->whereIn('role', ['employee', 'group_lead'])
            ->whereDoesntHave('supervisedDepartment')
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department_id', 'role']);

        return view('admin.work-groups.index', compact('departments', 'groups', 'leaders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('work_groups', 'name')->where('department_id', $request->input('department_id')),
            ],
            'leader_id' => ['nullable', 'integer'],
        ]);
        $department = Department::query()->findOrFail($attributes['department_id']);
        $leader = $this->validatedLeader($attributes['leader_id'] ?? null, $department);

        DB::transaction(function () use ($attributes, $department, $leader) {
            $group = WorkGroup::query()->create([
                'department_id' => $department->id,
                'name' => $attributes['name'],
                'leader_id' => $leader?->id,
            ]);
            if ($leader) {
                $this->assignLeader($leader, $group);
            }
        });

        return redirect()->route('admin.work-groups.index')->with('success', 'Group berhasil ditambahkan.');
    }

    public function update(Request $request, WorkGroup $workGroup): RedirectResponse
    {
        $attributes = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('work_groups', 'name')
                    ->where('department_id', $workGroup->department_id)
                    ->ignore($workGroup->id),
            ],
            'leader_id' => ['nullable', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);
        $leader = $this->validatedLeader($attributes['leader_id'] ?? null, $workGroup->department, $workGroup->id);
        $previousLeader = $workGroup->leader;

        if (! $attributes['is_active'] && $workGroup->employees()->exists()) {
            throw ValidationException::withMessages([
                'is_active' => 'Pindahkan anggota group sebelum menonaktifkannya.',
            ]);
        }

        DB::transaction(function () use ($attributes, $leader, $previousLeader, $workGroup) {
            $workGroup->update([
                'name' => $attributes['name'],
                'leader_id' => $leader?->id,
                'is_active' => $attributes['is_active'],
            ]);

            if ($previousLeader && $previousLeader->id !== $leader?->id) {
                $previousLeader->update([
                    'role' => $previousLeader->role === 'group_lead' ? 'employee' : $previousLeader->role,
                    'work_group_id' => null,
                ]);
            }
            if ($leader) {
                $this->assignLeader($leader, $workGroup);
            }
        });

        return redirect()->route('admin.work-groups.index')->with('success', 'Group berhasil diperbarui.');
    }

    private function validatedLeader(?int $leaderId, Department $department, ?int $currentGroupId = null): ?User
    {
        if ($leaderId === null) {
            return null;
        }

        $leader = User::query()->find($leaderId);
        $ledGroups = $leader?->ledWorkGroup();
        if ($ledGroups && $currentGroupId) {
            $ledGroups->where('id', '!=', $currentGroupId);
        }

        if (
            ! $leader
            || $leader->department_id !== $department->id
            || ! in_array($leader->role, ['employee', 'group_lead'], true)
            || $leader->supervisedDepartment()->exists()
            || $ledGroups?->exists()
        ) {
            throw ValidationException::withMessages([
                'leader_id' => 'Ketua group harus karyawan di departemen ini dan hanya boleh memimpin satu group.',
            ]);
        }

        return $leader;
    }

    private function assignLeader(User $leader, WorkGroup $group): void
    {
        $leader->update([
            'role' => 'group_lead',
            'work_group_id' => $group->id,
        ]);
    }
}
