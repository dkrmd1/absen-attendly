<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use App\Notifications\AttendanceCorrectionWorkflowNotification;
use Illuminate\Database\Eloquent\Collection;

class AttendanceCorrectionNotificationService
{
    public function notifyReviewers(AttendanceCorrectionRequest $record): void
    {
        $recipients = match ($record->status) {
            'pending_group' => $this->groupLeader($record),
            'pending_division' => $this->divisionHead($record),
            'pending_director' => $this->director($record),
            'pending_ceo' => $this->activeRole('ceo', $record->user_id),
            'pending_commissioner' => $this->activeRole('commissioner', $record->user_id),
            'pending_hr' => $this->activeRole('hr', $record->user_id),
            default => new Collection,
        };

        $stage = match ($record->status) {
            'pending_group' => 'Ketua Tim',
            'pending_division' => 'Kepala Divisi',
            'pending_director' => 'Direktur',
            'pending_ceo' => 'Direktur Utama',
            'pending_commissioner' => 'Komisaris',
            'pending_hr' => 'HR untuk menerapkan perubahan',
            default => null,
        };
        if (! $stage || $recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $recipient) {
            $recipient->notify(new AttendanceCorrectionWorkflowNotification(
                'Koreksi absensi perlu ditinjau',
                "{$record->user?->name} mengajukan koreksi absensi yang menunggu {$stage}.",
                [
                    'kind' => 'attendance_correction_review',
                    'attendance_correction_request_id' => $record->id,
                    'status' => $record->status,
                ],
            ));
        }
    }

    public function notifyApplicant(
        AttendanceCorrectionRequest $record,
        string $stage,
        string $decision,
        string $nextStatus,
    ): void {
        $stageLabel = match ($stage) {
            'group' => 'Ketua Tim',
            'division' => 'Kepala Divisi',
            'director' => 'Direktur',
            'ceo' => 'Direktur Utama',
            'commissioner' => 'Komisaris',
            'apply' => 'HR',
            default => $stage,
        };
        $message = match ($decision) {
            'rejected' => "Permohonan koreksi absensi ditolak oleh {$stageLabel}.",
            'applied' => 'Koreksi absensi sudah diterapkan oleh HR.',
            default => "Permohonan koreksi absensi disetujui {$stageLabel} dan diteruskan.",
        };

        $record->user?->notify(new AttendanceCorrectionWorkflowNotification(
            $decision === 'applied' || $decision === 'rejected'
                ? 'Keputusan koreksi absensi'
                : 'Pembaruan koreksi absensi',
            $message,
            [
                'kind' => 'attendance_correction_update',
                'attendance_correction_request_id' => $record->id,
                'decision' => $decision,
                'status' => $nextStatus,
            ],
        ));
    }

    private function groupLeader(AttendanceCorrectionRequest $record): Collection
    {
        $group = $record->workGroup;
        if (! $group?->is_active || ! $group->leader_id || $group->leader_id === $record->user_id) {
            return new Collection;
        }

        return User::query()->whereKey($group->leader_id)
            ->where('role', 'group_lead')
            ->where('is_active', true)
            ->where('work_group_id', $record->work_group_id)
            ->get();
    }

    private function divisionHead(AttendanceCorrectionRequest $record): Collection
    {
        $department = $record->department;
        if (! $department?->is_active || ! $department->supervisor_id) {
            return new Collection;
        }

        return User::query()->whereKey($department->supervisor_id)
            ->whereIn('role', ['employee', 'division_head'])
            ->where('is_active', true)
            ->whereKeyNot($record->user_id)
            ->get();
    }

    private function director(AttendanceCorrectionRequest $record): Collection
    {
        $directorId = $record->department?->director_id;
        $query = User::query()->where('role', 'director')->where('is_active', true);

        return $directorId
            ? $query->whereKey($directorId)->whereKeyNot($record->user_id)->get()
            : ($record->department_id
                ? new Collection
                : $query->whereKeyNot($record->user_id)->get());
    }

    private function activeRole(string $role, int $exceptUserId): Collection
    {
        return User::query()->where('role', $role)
            ->where('is_active', true)
            ->whereKeyNot($exceptUserId)
            ->get();
    }
}
