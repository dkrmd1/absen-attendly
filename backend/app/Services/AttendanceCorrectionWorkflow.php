<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkGroup;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionWorkflow
{
    public function initialStatus(User $applicant, ?int $departmentId, ?int $workGroupId): string
    {
        if ($applicant->isCeo() || $applicant->isCommissioner()) {
            $this->requireOtherCommissioner($applicant);

            return 'pending_commissioner';
        }

        if ($applicant->isDirector()) {
            $this->requireActiveReviewer('ceo', 'Permohonan koreksi Direktur memerlukan Direktur Utama aktif.');

            return 'pending_ceo';
        }

        if ($applicant->isHr() || $applicant->isDivisionHead()) {
            $this->requireDepartmentDirector($departmentId);

            return 'pending_director';
        }

        if ($workGroupId) {
            $leader = WorkGroup::query()
                ->whereKey($workGroupId)
                ->where('is_active', true)
                ->first();
            if ($leader?->leader_id && $leader->leader_id !== $applicant->id) {
                $hasActiveLeader = User::query()
                    ->whereKey($leader->leader_id)
                    ->where('role', 'group_lead')
                    ->where('is_active', true)
                    ->exists();
                if ($hasActiveLeader) {
                    return 'pending_group';
                }
            }
        }

        if ($this->activeDepartmentSupervisor($departmentId, $applicant->id)) {
            return 'pending_division';
        }

        $this->requireActiveReviewer('hr', 'Permohonan koreksi memerlukan HR aktif untuk menerapkan perubahan.');

        return 'pending_hr';
    }

    public function stageFor(User $reviewer, AttendanceCorrectionRequest $record): ?string
    {
        if (! $reviewer->is_active || $reviewer->id === $record->user_id) {
            return null;
        }

        if ($record->status === 'pending_group' && $reviewer->isGroupLead()) {
            return $record->work_group_id === $reviewer->work_group_id
                && $record->workGroup?->leader_id === $reviewer->id
                ? 'group'
                : null;
        }

        if ($record->status === 'pending_division' && $reviewer->isDivisionHead()) {
            return $record->department_id === $reviewer->department_id
                && $record->department?->supervisor_id === $reviewer->id
                ? 'division'
                : null;
        }

        if ($record->status === 'pending_director' && $reviewer->isDirector()) {
            return (! $record->department_id || $record->department?->director_id === $reviewer->id)
                ? 'director'
                : null;
        }

        if ($record->status === 'pending_ceo' && $reviewer->isCeo()) {
            return 'ceo';
        }

        if ($record->status === 'pending_commissioner' && $reviewer->isCommissioner()) {
            return 'commissioner';
        }

        if ($record->status === 'pending_hr' && $reviewer->isHr()) {
            return 'apply';
        }

        return null;
    }

    public function nextStatus(string $stage, AttendanceCorrectionRequest $record): string
    {
        $nextStatus = match ($stage) {
            'group' => $this->activeDepartmentSupervisor($record->department_id, $record->user_id)
                ? 'pending_division'
                : 'pending_hr',
            'division', 'director', 'ceo', 'commissioner' => 'pending_hr',
            default => throw new \InvalidArgumentException('Tahap koreksi absensi tidak dikenal.'),
        };

        if ($nextStatus === 'pending_hr') {
            $this->requireActiveReviewer(
                'hr',
                'Koreksi tidak dapat diteruskan karena tidak ada HR aktif lain yang dapat menerapkan perubahan.',
                $record->user_id,
            );
        }

        return $nextStatus;
    }

    public function canSee(User $viewer, AttendanceCorrectionRequest $record): bool
    {
        if ($viewer->id === $record->user_id || $viewer->isAdmin() || $viewer->isHr()) {
            return true;
        }

        if ($viewer->isGroupLead()) {
            return $record->work_group_id === $viewer->work_group_id;
        }

        if ($viewer->isDivisionHead()) {
            return $record->department_id === $viewer->department_id;
        }

        if ($viewer->isDirector()) {
            return $record->status === 'pending_director'
                && (! $record->department_id || $record->department?->director_id === $viewer->id);
        }

        if ($viewer->isCeo()) {
            return $record->status === 'pending_ceo';
        }

        if ($viewer->isCommissioner()) {
            return $record->status === 'pending_commissioner';
        }

        return false;
    }

    private function activeDepartmentSupervisor(?int $departmentId, int $applicantId): bool
    {
        if (! $departmentId) {
            return false;
        }

        $department = Department::query()->whereKey($departmentId)->where('is_active', true)->first();

        return $department?->supervisor_id !== null
            && $department->supervisor_id !== $applicantId
            && User::query()
                ->whereKey($department->supervisor_id)
                ->whereIn('role', ['employee', 'division_head'])
                ->where('is_active', true)
                ->exists();
    }

    private function requireDepartmentDirector(?int $departmentId): void
    {
        $directorId = $departmentId
            ? Department::query()->whereKey($departmentId)->value('director_id')
            : null;
        $directorQuery = User::query()->where('role', 'director')->where('is_active', true);
        $directorExists = $departmentId
            ? $directorId !== null && $directorQuery->whereKey($directorId)->exists()
            : $directorQuery->exists();

        if (! $directorExists) {
            throw ValidationException::withMessages([
                'request' => 'Pengajuan koreksi HR/Kepala Divisi memerlukan Direktur aktif yang ditugaskan.',
            ]);
        }
    }

    private function requireOtherCommissioner(User $applicant): void
    {
        $exists = User::query()
            ->where('role', 'commissioner')
            ->where('is_active', true)
            ->whereKeyNot($applicant->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'request' => 'Permohonan pimpinan puncak memerlukan Komisaris lain yang aktif sebagai peninjau.',
            ]);
        }
    }

    private function requireActiveReviewer(string $role, string $message, ?int $exceptUserId = null): void
    {
        $query = User::query()->where('role', $role)->where('is_active', true);
        if ($exceptUserId) {
            $query->whereKeyNot($exceptUserId);
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages(['request' => $message]);
        }
    }
}
