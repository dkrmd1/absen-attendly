<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestWorkflowNotification;
use Illuminate\Database\Eloquent\Collection;

class LeaveRequestNotificationService
{
    public function notifyReviewers(LeaveRequest $request): void
    {
        $recipients = match ($request->status) {
            'pending_group' => $this->groupLeader($request),
            'pending', 'pending_division' => $this->divisionHead($request),
            'pending_director' => $this->directors($request),
            'pending_ceo' => User::query()->where('role', 'ceo')
                ->where('is_active', true)->whereKeyNot($request->user_id)->get(),
            'pending_commissioner' => User::query()->where('role', 'commissioner')
                ->where('is_active', true)->whereKeyNot($request->user_id)->get(),
            'pending_hr' => User::query()->where('role', 'hr')
                ->where('is_active', true)->whereKeyNot($request->user_id)->get(),
            default => new Collection,
        };

        $stage = match ($request->status) {
            'pending_group' => 'Group Head',
            'pending', 'pending_division' => 'Pimpinan Divisi',
            'pending_director' => 'direktur',
            'pending_ceo' => 'direktur utama',
            'pending_commissioner' => 'komisaris',
            'pending_hr' => 'HR',
            default => null,
        };
        if (! $stage || $recipients->isEmpty()) {
            return;
        }

        $applicantName = $request->user?->name ?? 'Karyawan';
        foreach ($recipients as $recipient) {
            $recipient->notify(new LeaveRequestWorkflowNotification(
                'Pengajuan perlu ditinjau',
                "{$applicantName} mengirim pengajuan yang menunggu persetujuan {$stage}.",
                [
                    'kind' => 'leave_request_review',
                    'leave_request_id' => $request->id,
                    'status' => $request->status,
                ],
            ));
        }
    }

    public function notifyApplicant(
        LeaveRequest $request,
        User $reviewer,
        string $stage,
        string $decision,
        string $nextStatus,
    ): void {
        $approved = $decision === 'approved';
        $final = in_array($nextStatus, ['approved', 'rejected'], true);
        $stageLabel = match ($stage) {
            'group' => 'Group Head',
            'division' => 'Pimpinan Divisi',
            'director' => 'direktur',
            'ceo' => 'direktur utama',
            'commissioner' => 'komisaris',
            'hr' => 'HR',
            default => $stage,
        };
        $message = $approved
            ? ($final
                ? "Pengajuan kamu disetujui oleh {$stageLabel}."
                : "Pengajuan kamu disetujui oleh {$stageLabel} dan diteruskan ke tahap berikutnya.")
            : "Pengajuan kamu ditolak oleh {$stageLabel}.";

        $request->user?->notify(new LeaveRequestWorkflowNotification(
            $final ? 'Keputusan pengajuan' : 'Pembaruan pengajuan',
            $message,
            [
                'kind' => 'leave_request_update',
                'leave_request_id' => $request->id,
                'decision' => $decision,
                'status' => $nextStatus,
            ],
        ));
    }

    private function groupLeader(LeaveRequest $request): Collection
    {
        $leader = $request->workGroup;
        if (! $leader?->is_active || ! $leader->leader_id || $leader->leader_id === $request->user_id) {
            return new Collection;
        }

        return User::query()
            ->whereKey($leader->leader_id)
            ->where('role', 'group_lead')
            ->where('is_active', true)
            ->where('work_group_id', $request->work_group_id)
            ->get();
    }

    private function divisionHead(LeaveRequest $request): Collection
    {
        $department = $request->department;
        if (! $department?->is_active || ! $department->supervisor_id) {
            return new Collection;
        }

        return User::query()
            ->whereKey($department->supervisor_id)
            ->whereIn('role', ['employee', 'division_head'])
            ->where('is_active', true)
            ->whereKeyNot($request->user_id)
            ->get();
    }

    private function directors(LeaveRequest $request): Collection
    {
        $department = $request->department;
        if ($request->department_id && ! $department?->director_id) {
            return new Collection;
        }

        if ($department?->director_id) {
            return User::query()
                ->whereKey($department->director_id)
                ->where('role', 'director')
                ->where('is_active', true)
                ->whereKeyNot($request->user_id)
                ->get();
        }

        return User::query()
            ->where('role', 'director')
            ->where('is_active', true)
            ->whereKeyNot($request->user_id)
            ->get();
    }
}
