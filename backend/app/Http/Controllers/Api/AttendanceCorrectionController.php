<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Services\AttendanceCorrectionNotificationService;
use App\Services\AttendanceCorrectionWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $corrections = $request->user()->attendanceCorrectionRequests()
            ->with('attendance:id,work_date')
            ->latest()
            ->paginate(30);
        $corrections->through(fn (AttendanceCorrectionRequest $record) => $this->requestData($record));

        return response()->json($corrections);
    }

    public function store(
        Request $request,
        Attendance $attendance,
        AttendanceCorrectionWorkflow $workflow,
        AttendanceCorrectionNotificationService $notifications,
    ): JsonResponse {
        $attributes = $request->validate([
            'check_in_at' => ['required', 'date', 'before_or_equal:now'],
            'check_out_at' => ['nullable', 'date', 'after_or_equal:check_in_at', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        abort_unless($attendance->user_id === $request->user()->id, 404);
        $checkInAt = CarbonImmutable::parse($attributes['check_in_at']);
        $checkOutAt = isset($attributes['check_out_at'])
            ? CarbonImmutable::parse($attributes['check_out_at'])
            : null;
        if ($checkInAt->toDateString() !== $attendance->work_date->toDateString()) {
            throw ValidationException::withMessages([
                'check_in_at' => 'Jam masuk koreksi harus tetap berada pada tanggal absensi.',
            ]);
        }

        $record = DB::transaction(function () use (
            $request,
            $attendance,
            $attributes,
            $checkInAt,
            $checkOutAt,
            $workflow,
            $notifications,
        ) {
            $lockedAttendance = Attendance::query()
                ->whereKey($attendance->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedAttendance->user_id === $request->user()->id, 404);

            $checkInUnchanged = $checkInAt->equalTo($lockedAttendance->check_in_at);
            $checkOutUnchanged = $checkOutAt === null
                ? $lockedAttendance->check_out_at === null
                : $lockedAttendance->check_out_at !== null
                    && $checkOutAt->equalTo($lockedAttendance->check_out_at);
            if ($checkInUnchanged && $checkOutUnchanged) {
                throw ValidationException::withMessages([
                    'check_in_at' => 'Tidak ada perubahan jam yang diajukan.',
                ]);
            }

            $hasPendingRequest = $lockedAttendance->correctionRequests()
                ->whereIn('status', [
                    'pending_group',
                    'pending_division',
                    'pending_director',
                    'pending_ceo',
                    'pending_commissioner',
                    'pending_hr',
                ])
                ->exists();
            if ($hasPendingRequest) {
                throw ValidationException::withMessages([
                    'attendance' => 'Absensi ini masih memiliki permohonan koreksi yang belum selesai.',
                ]);
            }

            $applicant = $request->user();
            $correction = AttendanceCorrectionRequest::query()->create([
                'attendance_id' => $lockedAttendance->id,
                'user_id' => $applicant->id,
                'department_id' => $lockedAttendance->department_id,
                'work_group_id' => $applicant->work_group_id,
                'original_check_in_at' => $lockedAttendance->check_in_at,
                'original_check_out_at' => $lockedAttendance->check_out_at,
                'requested_check_in_at' => $checkInAt,
                'requested_check_out_at' => $checkOutAt,
                'reason' => $attributes['reason'],
                'status' => $workflow->initialStatus(
                    $applicant,
                    $lockedAttendance->department_id,
                    $applicant->work_group_id,
                ),
            ]);
            $notifications->notifyReviewers($correction);

            return $correction;
        });

        return response()->json(['data' => $this->requestData($record)], 201);
    }

    private function requestData(AttendanceCorrectionRequest $record): array
    {
        return [
            'id' => $record->id,
            'attendance_id' => $record->attendance_id,
            'work_date' => $record->attendance?->work_date?->toDateString(),
            'original_check_in_at' => $record->original_check_in_at?->toIso8601String(),
            'original_check_out_at' => $record->original_check_out_at?->toIso8601String(),
            'check_in_at' => $record->requested_check_in_at->toIso8601String(),
            'check_out_at' => $record->requested_check_out_at?->toIso8601String(),
            'reason' => $record->reason,
            'status' => $record->status,
            'application_note' => $record->application_note,
            'applied_at' => $record->applied_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
            'reviews' => $record->reviews()
                ->with('reviewer:id,name,role')
                ->get()
                ->map(fn ($review) => [
                    'stage' => $review->stage,
                    'decision' => $review->decision,
                    'note' => $review->note,
                    'reviewer' => $review->reviewer?->name,
                    'reviewed_at' => $review->reviewed_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
