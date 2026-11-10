<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrectionReview;
use App\Services\AttendanceCorrectionNotificationService;
use App\Services\AttendanceCorrectionWorkflow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request, AttendanceCorrectionWorkflow $workflow): View
    {
        $viewer = $request->user();
        $query = AttendanceCorrectionRequest::query()
            ->with([
                'user:id,name,email,role',
                'attendance:id,work_date,check_in_at,check_out_at,late_minutes',
                'department:id,name',
                'workGroup:id,name',
                'applier:id,name',
                'reviews.reviewer:id,name,role',
            ]);

        if ($viewer->isAdmin() || $viewer->isHr()) {
            // HR and administrators can inspect the company-wide audit queue.
        } elseif ($viewer->isDirector()) {
            $query->where(fn ($records) => $records
                ->where(fn ($pending) => $pending
                    ->where('status', 'pending_director')
                    ->where(fn ($department) => $department
                        ->whereNull('department_id')
                        ->orWhereHas('department', fn ($assigned) => $assigned->where('director_id', $viewer->id))))
                ->orWhere('user_id', $viewer->id));
        } elseif ($viewer->isCeo()) {
            $query->where(fn ($records) => $records
                ->where('status', 'pending_ceo')
                ->orWhere('user_id', $viewer->id));
        } elseif ($viewer->isCommissioner()) {
            $query->where(fn ($records) => $records
                ->where('status', 'pending_commissioner')
                ->orWhere('user_id', $viewer->id));
        } elseif ($viewer->isGroupLead()) {
            $query->where(fn ($records) => $records
                ->where('work_group_id', $viewer->work_group_id)
                ->orWhere('user_id', $viewer->id));
        } elseif ($viewer->isDivisionHead()) {
            $query->where(fn ($records) => $records
                ->where('department_id', $viewer->department_id)
                ->orWhere('user_id', $viewer->id));
        } else {
            abort(403);
        }

        $corrections = $query->latest()->paginate(20)->withQueryString();
        $reviewStages = $corrections->getCollection()->mapWithKeys(fn (AttendanceCorrectionRequest $record) => [
            $record->id => $workflow->stageFor($viewer, $record),
        ]);

        return view('admin.attendance-corrections.index', compact('corrections', 'reviewStages'));
    }

    public function update(
        Request $request,
        AttendanceCorrectionRequest $correction,
        AttendanceCorrectionWorkflow $workflow,
        AttendanceCorrectionNotificationService $notifications,
    ): RedirectResponse {
        $stage = $workflow->stageFor($request->user(), $correction);
        abort_unless($stage, 404);

        $attributes = $request->validate([
            'decision' => ['required', 'in:approved,rejected,applied'],
            'note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:1000'],
        ]);
        if ($stage === 'apply' && ! in_array($attributes['decision'], ['rejected', 'applied'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'HR hanya dapat menerapkan atau menolak koreksi pada tahap akhir.',
            ]);
        }
        if ($stage !== 'apply' && ! in_array($attributes['decision'], ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Tahap ini hanya dapat menyetujui atau menolak koreksi.',
            ]);
        }

        DB::transaction(function () use ($request, $correction, $attributes, $stage, $workflow, $notifications) {
            $record = AttendanceCorrectionRequest::query()
                ->whereKey($correction->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedStage = $workflow->stageFor($request->user(), $record);
            abort_unless($lockedStage === $stage, 404);

            $reviewedAt = now();
            $decision = $attributes['decision'];
            if ($decision === 'rejected') {
                $record->update(['status' => 'rejected']);
                $nextStatus = 'rejected';
            } elseif ($stage === 'apply') {
                $attendance = Attendance::query()
                    ->whereKey($record->attendance_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->ensureSnapshotIsCurrent($record, $attendance);

                $attendance->update([
                    'check_in_at' => $record->requested_check_in_at,
                    'check_out_at' => $record->requested_check_out_at,
                    'late_minutes' => $this->calculateLateMinutes($attendance, $record->requested_check_in_at),
                ]);
                $record->update([
                    'status' => 'applied',
                    'applied_by' => $request->user()->id,
                    'applied_at' => $reviewedAt,
                    'application_note' => $attributes['note'] ?? null,
                ]);
                $nextStatus = 'applied';
            } else {
                $nextStatus = $workflow->nextStatus($stage, $record);
                $record->update(['status' => $nextStatus]);
            }

            AttendanceCorrectionReview::query()->create([
                'correction_request_id' => $record->id,
                'reviewer_id' => $request->user()->id,
                'stage' => $stage,
                'decision' => $decision,
                'note' => $attributes['note'] ?? null,
                'reviewed_at' => $reviewedAt,
            ]);

            $notifications->notifyApplicant($record, $stage, $decision, $nextStatus);
            if ($decision === 'approved' && ! in_array($nextStatus, ['rejected', 'applied'], true)) {
                $notifications->notifyReviewers($record);
            }
        });

        return redirect()->route('admin.attendance-corrections.index')
            ->with('success', 'Permohonan koreksi absensi berhasil diperbarui.');
    }

    private function ensureSnapshotIsCurrent(
        AttendanceCorrectionRequest $correction,
        Attendance $attendance,
    ): void {
        $sameCheckIn = $correction->original_check_in_at->equalTo($attendance->check_in_at);
        $sameCheckOut = $correction->original_check_out_at === null
            ? $attendance->check_out_at === null
            : $attendance->check_out_at !== null
                && $correction->original_check_out_at->equalTo($attendance->check_out_at);

        if (! $sameCheckIn || ! $sameCheckOut) {
            throw ValidationException::withMessages([
                'decision' => 'Catatan absensi berubah setelah permohonan dibuat. Koreksi ini tidak dapat diterapkan.',
            ]);
        }
    }

    private function calculateLateMinutes(Attendance $attendance, CarbonInterface $checkInAt): ?int
    {
        if (! $attendance->shift_start_time || $attendance->is_scheduled_workday !== true) {
            return null;
        }

        $shiftStart = CarbonImmutable::parse(
            $attendance->work_date->toDateString().' '.$attendance->shift_start_time,
            config('app.timezone'),
        );
        $secondsLate = max(0, $shiftStart->diffInSeconds($checkInAt, false));
        $secondsAfterGrace = $secondsLate - (($attendance->shift_grace_period_minutes ?? 0) * 60);

        return $secondsAfterGrace > 0
            ? (int) ceil($secondsAfterGrace / 60)
            : 0;
    }
}
