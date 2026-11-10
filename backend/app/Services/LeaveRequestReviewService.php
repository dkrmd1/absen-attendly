<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestReview;
use App\Models\User;
use App\Models\WorkAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveRequestReviewService
{
    public function review(
        Request $request,
        LeaveRequest $leaveRequest,
        LeaveApprovalWorkflow $workflow,
        LeaveRequestNotificationService $notifications,
    ): LeaveRequest {
        $stage = $workflow->stageFor($request->user(), $leaveRequest);
        abort_unless($stage, 404);
        $isFinalReviewer = $workflow->isFinalReviewer($stage, $leaveRequest);

        $attributes = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:1000'],
            'work_location_id' => [
                Rule::requiredIf(
                    $isFinalReviewer
                    && $request->input('status') === 'approved'
                    && in_array($leaveRequest->type, ['perjalanan_dinas', 'diklat'], true)
                    && ! $request->boolean('is_online')
                ),
                'nullable',
                Rule::exists('work_locations', 'id')->where('is_active', true),
            ],
            'is_online' => ['nullable', 'boolean'],
        ]);

        $isOnline = filter_var($attributes['is_online'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($isOnline && (! $isFinalReviewer || $leaveRequest->type !== 'diklat')) {
            throw ValidationException::withMessages([
                'is_online' => 'Hanya peninjau akhir pengajuan diklat yang dapat menandainya online.',
            ]);
        }
        if (
            $isFinalReviewer
            && $attributes['status'] === 'approved'
            && $leaveRequest->type === 'diklat'
            && ! $isOnline
            && empty($attributes['work_location_id'])
        ) {
            throw ValidationException::withMessages([
                'work_location_id' => 'Pilih lokasi diklat tatap muka atau tandai sebagai diklat online.',
            ]);
        }

        DB::transaction(function () use (
            $request,
            $leaveRequest,
            $attributes,
            $isOnline,
            $stage,
            $workflow,
            $notifications,
        ) {
            $record = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($workflow->stageFor($request->user(), $record) === $stage, 404);

            $employee = User::query()->whereKey($record->user_id)->lockForUpdate()->firstOrFail();
            $nextStatus = $attributes['status'] === 'rejected'
                ? 'rejected'
                : $workflow->nextStatus($stage, $record);

            if (
                $nextStatus === 'approved'
                && $record->type === 'cuti'
                && $record->subtype === 'cuti_tahunan'
            ) {
                app(LeaveBalanceService::class)->assertAvailable(
                    $employee,
                    CarbonImmutable::parse($record->start_date)->startOfDay(),
                    CarbonImmutable::parse($record->end_date)->endOfDay(),
                );
            }

            $reviewedAt = now();
            $updates = ['status' => $nextStatus];
            if ($stage === 'group') {
                $updates += [
                    'group_status' => $attributes['status'],
                    'group_reviewed_by' => $request->user()->id,
                    'group_reviewed_at' => $reviewedAt,
                ];
            } elseif ($stage === 'division') {
                $updates += [
                    'supervisor_status' => $attributes['status'],
                    'supervisor_reviewed_by' => $request->user()->id,
                    'supervisor_reviewed_at' => $reviewedAt,
                ];
            } elseif ($stage === 'director') {
                $updates += [
                    'director_status' => $attributes['status'],
                    'director_reviewed_by' => $request->user()->id,
                    'director_reviewed_at' => $reviewedAt,
                ];
            } elseif ($stage === 'ceo') {
                $updates += [
                    'ceo_status' => $attributes['status'],
                    'ceo_reviewed_by' => $request->user()->id,
                    'ceo_reviewed_at' => $reviewedAt,
                ];
            } elseif ($stage === 'commissioner') {
                $updates += [
                    'commissioner_status' => $attributes['status'],
                    'commissioner_reviewed_by' => $request->user()->id,
                    'commissioner_reviewed_at' => $reviewedAt,
                ];
            }
            if ($attributes['status'] === 'rejected' || $nextStatus === 'approved') {
                $updates += [
                    'review_note' => $attributes['review_note'] ?? null,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => $reviewedAt,
                ];
            }
            $record->update($updates);

            LeaveRequestReview::query()->create([
                'leave_request_id' => $record->id,
                'reviewer_id' => $request->user()->id,
                'stage' => $stage,
                'decision' => $attributes['status'],
                'note' => $attributes['review_note'] ?? null,
                'reviewed_at' => $reviewedAt,
            ]);

            $notifications->notifyApplicant(
                $record,
                $request->user(),
                $stage,
                $attributes['status'],
                $nextStatus,
            );
            if ($attributes['status'] === 'approved' && ! in_array($nextStatus, ['approved', 'rejected'], true)) {
                $notifications->notifyReviewers($record);
            }

            if ($nextStatus === 'approved' && in_array($record->type, ['perjalanan_dinas', 'diklat'], true)) {
                $hasOverlappingAssignment = WorkAssignment::query()
                    ->where('user_id', $record->user_id)
                    ->whereDate('start_date', '<=', $record->end_date)
                    ->whereDate('end_date', '>=', $record->start_date)
                    ->exists();
                if ($hasOverlappingAssignment) {
                    throw ValidationException::withMessages([
                        'status' => 'Jadwal penugasan karyawan bertabrakan dengan penugasan yang sudah disetujui.',
                    ]);
                }

                WorkAssignment::query()->create([
                    'user_id' => $record->user_id,
                    'work_location_id' => $isOnline ? null : ($attributes['work_location_id'] ?? null),
                    'leave_request_id' => $record->id,
                    'approved_by' => $request->user()->id,
                    'type' => $record->type,
                    'start_date' => $record->start_date,
                    'end_date' => $record->end_date,
                    'is_online' => $isOnline,
                ]);
            }
        });

        return $leaveRequest->refresh();
    }
}
