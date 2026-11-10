<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class LeaveApprovalWorkflow
{
    public function canReview(User $reviewer): bool
    {
        return $reviewer->is_active && (
            $reviewer->isGroupLead()
            || $reviewer->isDivisionHead()
            || $reviewer->isDirector()
            || $reviewer->isCeo()
            || $reviewer->isCommissioner()
            || $reviewer->isHr()
        );
    }

    public function reviewableQuery(User $reviewer): Builder
    {
        abort_unless($this->canReview($reviewer), 403);

        $query = LeaveRequest::query();
        if ($reviewer->isGroupLead()) {
            return $query->where('status', 'pending_group')
                ->where('work_group_id', $reviewer->work_group_id);
        }

        if ($reviewer->isDivisionHead()) {
            return $query->whereIn('status', ['pending', 'pending_division'])
                ->where('department_id', $reviewer->department_id);
        }

        if ($reviewer->isDirector()) {
            return $query->where('status', 'pending_director')
                ->where(fn (Builder $records) => $records
                    ->whereNull('department_id')
                    ->orWhereHas('department', fn (Builder $department) => $department
                        ->where('director_id', $reviewer->id)));
        }

        if ($reviewer->isCeo()) {
            return $query->where('status', 'pending_ceo');
        }

        if ($reviewer->isCommissioner()) {
            return $query->where('status', 'pending_commissioner');
        }

        return $query->where('status', 'pending_hr');
    }

    public function isFinalReviewer(string $stage, LeaveRequest $record): bool
    {
        return in_array($stage, ['group', 'hr', 'commissioner'], true)
            || ($stage === 'division' && in_array($record->requester_role, ['employee', 'group_lead'], true))
            || ($stage === 'director' && in_array($record->requester_role, ['hr', 'division_head', 'group_lead'], true))
            || ($stage === 'ceo' && $record->requester_role === 'director');
    }

    public function initialStatus(User $applicant): string
    {
        if ($applicant->isCeo() || $applicant->isCommissioner()) {
            $otherCommissionerExists = User::query()
                ->where('role', 'commissioner')
                ->where('is_active', true)
                ->whereKeyNot($applicant->id)
                ->exists();
            if (! $otherCommissionerExists) {
                throw ValidationException::withMessages([
                    'request' => 'Permohonan pimpinan puncak memerlukan Komisaris lain yang aktif sebagai peninjau.',
                ]);
            }

            return 'pending_commissioner';
        }

        if ($applicant->isDirector()) {
            $this->requireActiveReviewer('ceo', 'Pengajuan Direktur memerlukan Direktur Utama aktif.');

            return 'pending_ceo';
        }

        if ($applicant->isHr() || $applicant->isDivisionHead()) {
            $this->requireDirector($applicant);

            return 'pending_director';
        }

        if ($applicant->isGroupLead()) {
            $department = $applicant->department;
            $supervisor = $department?->supervisor;

            if (
                $department?->is_active
                && $supervisor
                && $supervisor->is_active
                && $supervisor->id !== $applicant->id
                && in_array($supervisor->role, ['employee', 'division_head'], true)
            ) {
                return 'pending_division';
            }

            return 'pending_hr';
        }

        if (
            $applicant->isGroupLead() === false
            && $applicant->role === 'employee'
            && $applicant->work_group_id
            && $applicant->workGroup?->is_active
            && $applicant->workGroup->leader_id
            && $applicant->workGroup->leader_id !== $applicant->id
            && User::query()->whereKey($applicant->workGroup->leader_id)
                ->where('role', 'group_lead')->where('is_active', true)->exists()
        ) {
            return 'pending_group';
        }

        $department = $applicant->department;
        $supervisor = $department?->supervisor;

        if (
            $department?->is_active
            && $supervisor
            && $supervisor->is_active
            && $supervisor->id !== $applicant->id
            && in_array($supervisor->role, ['employee', 'division_head'], true)
        ) {
            return 'pending';
        }

        return 'pending_hr';
    }

    public function stageFor(User $reviewer, LeaveRequest $record): ?string
    {
        if ($reviewer->id === $record->user_id || ! $reviewer->is_active) {
            return null;
        }

        if ($record->status === 'pending_group' && $reviewer->isGroupLead()) {
            return $record->work_group_id === $reviewer->work_group_id
                && $record->workGroup?->leader_id === $reviewer->id
                ? 'group'
                : null;
        }

        if (
            in_array($record->status, ['pending', 'pending_division'], true)
            && $reviewer->isDivisionHead()
        ) {
            return $record->department_id === $reviewer->department_id
                ? 'division'
                : null;
        }

        if ($record->status === 'pending_director' && $reviewer->isDirector()) {
            $assignedDirectorId = $record->department?->director_id;

            return (! $record->department_id || $assignedDirectorId === $reviewer->id)
                && User::query()->whereKey($reviewer->id)->where('is_active', true)->exists()
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
            return 'hr';
        }

        return null;
    }

    public function nextStatus(string $stage, LeaveRequest $record): string
    {
        return match ($stage) {
            'group' => 'approved',
            'division' => match ($record->requester_role) {
                'division_head', 'hr' => $this->pendingDirectorStatus($record),
                'employee', 'group_lead' => 'approved',
                default => 'pending_hr',
            },
            'director' => match ($record->requester_role) {
                'hr', 'division_head', 'group_lead' => 'approved',
                'director' => $this->requireAndReturn('ceo', 'Pengajuan Direktur memerlukan Direktur Utama aktif.', 'pending_ceo'),
                default => 'pending_hr',
            },
            'ceo' => $record->requester_role === 'director' ? 'approved' : 'pending_hr',
            'commissioner' => 'approved',
            'hr' => 'approved',
            default => throw new \InvalidArgumentException('Tahap persetujuan tidak dikenal.'),
        };
    }

    public function canSee(User $viewer, LeaveRequest $record): bool
    {
        if ($viewer->id === $record->user_id) {
            return true;
        }

        if ($viewer->isAdmin() || $viewer->isHr()) {
            return true;
        }

        if ($viewer->isCommissioner()) {
            return $record->status === 'pending_commissioner';
        }

        if ($viewer->isCeo()) {
            return $record->status === 'pending_ceo';
        }

        if ($viewer->isDirector()) {
            return $record->status === 'pending_director'
                && (! $record->department_id || $record->department?->director_id === $viewer->id);
        }

        if ($viewer->isGroupLead()) {
            return $record->work_group_id === $viewer->work_group_id;
        }

        if ($viewer->isDivisionHead()) {
            return $record->department_id === $viewer->department_id;
        }

        return false;
    }

    private function pendingDirectorStatus(LeaveRequest $record): string
    {
        $department = $record->department;
        $directorId = $department?->director_id;
        $query = User::query()->where('role', 'director')->where('is_active', true);
        if ($record->department_id) {
            $query->whereKey($directorId ?? 0);
        }
        if (! $query->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan tidak dapat diteruskan karena belum ada Direktur aktif yang ditugaskan.',
            ]);
        }

        return 'pending_director';
    }

    private function requireDirector(User $applicant): void
    {
        $department = $applicant->department;
        $directorId = $department?->director_id;
        $query = User::query()->where('role', 'director')->where('is_active', true);
        if ($applicant->department_id) {
            $query->whereKey($directorId ?? 0);
        }
        if (! $query->exists()) {
            throw ValidationException::withMessages([
                'request' => 'Pengajuan HR/Kepala Divisi memerlukan Direktur aktif yang ditugaskan.',
            ]);
        }
    }

    private function requireActiveReviewer(string $role, string $message): void
    {
        if (! User::query()->where('role', $role)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['request' => $message]);
        }
    }

    private function requireAndReturn(
        string $role,
        string $message,
        string $status,
        ?int $exceptUserId = null,
    ): string {
        $query = User::query()->where('role', $role)->where('is_active', true);
        if ($exceptUserId) {
            $query->whereKeyNot($exceptUserId);
        }
        if (! $query->exists()) {
            throw ValidationException::withMessages(['status' => $message]);
        }

        return $status;
    }
}
