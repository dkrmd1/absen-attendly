<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkAssignment;
use App\Models\WorkLocation;
use App\Services\AttendanceNotificationService;
use App\Services\PlayIntegrityVerifier;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(private readonly PlayIntegrityVerifier $integrityVerifier) {}

    public function today(Request $request): JsonResponse
    {
        $attendance = $request->user()->attendances()
            ->whereDate('work_date', today())
            ->first();

        return response()->json(['data' => $attendance]);
    }

    public function settings(Request $request): JsonResponse
    {
        $context = $this->resolveWorkContext($request->user(), requireLocation: false);
        $approvedAbsence = $this->approvedAbsenceForToday($request->user());
        $location = $context['location'];
        $assignment = $context['assignment'];
        $shift = $request->user()->workShift()->where('is_active', true)->first();

        return response()->json([
            'data' => [
                'location_assigned' => $location !== null || $assignment?->is_online === true,
                'geofence_enabled' => $location?->geofenceEnabled() ?? false,
                'requires_location' => $location?->geofenceEnabled() ?? false,
                'online_training' => $assignment?->is_online ?? false,
                'office_name' => $assignment?->is_online ? 'Diklat online' : $location?->name,
                'office_latitude' => $assignment?->is_online ? null : $location?->latitude,
                'office_longitude' => $assignment?->is_online ? null : $location?->longitude,
                'radius_meters' => $location?->radius_meters,
                'selfie_required' => true,
                'attendance_blocked' => $approvedAbsence !== null,
                'attendance_block_reason' => $approvedAbsence
                    ? $this->absenceBlockMessage($approvedAbsence)
                    : null,
                'play_integrity_required' => $this->integrityVerifier->isEnabled(),
                'shift' => $shift ? [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'start_time' => substr($shift->start_time, 0, 5),
                    'end_time' => substr($shift->end_time, 0, 5),
                    'grace_period_minutes' => $shift->grace_period_minutes,
                    'work_days' => $shift->work_days,
                ] : null,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:from',
            ],
        ]);

        $query = $request->user()->attendances()->orderByDesc('work_date');

        if (isset($filters['from'])) {
            $query->whereDate('work_date', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->whereDate('work_date', '<=', $filters['to']);
        }

        $attendances = $query->paginate(31);
        $attendances->through(fn (Attendance $attendance) => [
            ...$attendance->toArray(),
            'has_check_in_selfie' => $this->hasSelfie($attendance->check_in_selfie_path),
            'has_check_out_selfie' => $this->hasSelfie($attendance->check_out_selfie_path),
        ]);

        return response()->json($attendances);
    }

    public function selfie(Request $request, int $attendance, string $type)
    {
        abort_unless(in_array($type, ['check-in', 'check-out'], true), 404);

        $record = $request->user()->attendances()->findOrFail($attendance);
        $path = $type === 'check-in'
            ? $record->check_in_selfie_path
            : $record->check_out_selfie_path;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $response = response()->file(Storage::disk('local')->path($path));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function checkIn(Request $request, AttendanceNotificationService $notifications): JsonResponse
    {
        $this->ensureNoApprovedAbsence($request->user());

        if ($request->user()->attendances()->whereDate('work_date', today())->exists()) {
            return response()->json([
                'message' => 'Absensi masuk hari ini sudah tercatat.',
            ], 409);
        }

        $context = $this->resolveWorkContext($request->user());
        $location = $this->validateLocation($request, $context['location'], $context['assignment']);
        $request->validate([
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
        $this->integrityVerifier->verify(
            $request->header('X-Play-Integrity'),
            $request->user()->id,
            'check-in',
            $location['latitude'],
            $location['longitude'],
            $this->selfieHash($request),
        );

        $now = now();
        $shift = $request->user()->workShift()->where('is_active', true)->first();
        $isScheduledWorkday = $shift
            ? in_array($now->dayOfWeekIso, $shift->work_days, true)
            : null;
        $lateMinutes = null;
        if ($shift && $isScheduledWorkday) {
            $shiftStart = $now->copy()->setTimeFromTimeString($shift->start_time);
            $secondsLate = max(0, $shiftStart->diffInSeconds($now, false));
            $secondsAfterGrace = $secondsLate - ($shift->grace_period_minutes * 60);
            $lateMinutes = $secondsAfterGrace > 0
                ? (int) ceil($secondsAfterGrace / 60)
                : 0;
        }

        $selfiePath = $request->file('selfie')->store('attendance-selfies', 'local');
        if (! $selfiePath) {
            throw new \RuntimeException('Selfie absensi gagal disimpan ke penyimpanan privat.');
        }
        $created = Attendance::query()->insertOrIgnore([
            'user_id' => $request->user()->id,
            'work_date' => $now->toDateString(),
            'check_in_at' => $now->toDateTimeString(),
            'check_in_latitude' => $location['latitude'] ?? null,
            'check_in_longitude' => $location['longitude'] ?? null,
            'work_location_id' => $context['location']?->id,
            'department_id' => $request->user()->department_id,
            'work_shift_id' => $shift?->id,
            'shift_name' => $shift?->name,
            'shift_start_time' => $shift?->start_time,
            'shift_end_time' => $shift?->end_time,
            'shift_grace_period_minutes' => $shift?->grace_period_minutes,
            'shift_work_days' => $shift ? json_encode($shift->work_days, JSON_THROW_ON_ERROR) : null,
            'is_scheduled_workday' => $isScheduledWorkday,
            'late_minutes' => $lateMinutes,
            'check_in_selfie_path' => $selfiePath,
            'selfie_review_status' => 'submitted',
            'work_assignment_id' => $context['assignment']?->id,
            'attendance_mode' => $context['assignment']?->is_online ? 'online_training' : 'onsite',
            'verification_status' => $context['assignment']?->is_online ? 'pending_review' : 'recorded',
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        if ($created === 0) {
            if ($selfiePath) {
                Storage::disk('local')->delete($selfiePath);
            }

            return response()->json([
                'message' => 'Absensi masuk hari ini sudah tercatat.',
            ], 409);
        }

        $attendance = $request->user()->attendances()
            ->whereDate('work_date', $now->toDateString())
            ->firstOrFail();

        try {
            $notifications->notifyLateCheckIn($attendance);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json(['data' => $attendance], 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $this->ensureNoApprovedAbsence($request->user());

        $existing = $request->user()->attendances()
            ->whereDate('work_date', today())
            ->first();

        if (! $existing || $existing->check_out_at !== null) {
            return response()->json([
                'message' => 'Absen masuk hari ini belum tercatat atau absen pulang sudah dilakukan.',
            ], 409);
        }

        $context = $this->resolveWorkContext($request->user());
        $location = $this->validateLocation($request, $context['location'], $context['assignment']);
        $request->validate([
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
        $this->integrityVerifier->verify(
            $request->header('X-Play-Integrity'),
            $request->user()->id,
            'check-out',
            $location['latitude'],
            $location['longitude'],
            $this->selfieHash($request),
        );

        $selfiePath = $request->file('selfie')->store('attendance-selfies', 'local');
        if (! $selfiePath) {
            throw new \RuntimeException('Selfie absensi pulang gagal disimpan ke penyimpanan privat.');
        }

        try {
            $attendance = DB::transaction(function () use ($request, $location, $selfiePath) {
                $record = $request->user()->attendances()
                    ->whereDate('work_date', today())
                    ->lockForUpdate()
                    ->first();

                if (! $record || $record->check_out_at !== null) {
                    return null;
                }

                $record->update([
                    'check_out_at' => now(),
                    'check_out_latitude' => $location['latitude'] ?? null,
                    'check_out_longitude' => $location['longitude'] ?? null,
                    'check_out_selfie_path' => $selfiePath,
                ]);

                return $record->fresh();
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($selfiePath);

            throw $error;
        }

        if (! $attendance) {
            Storage::disk('local')->delete($selfiePath);

            return response()->json([
                'message' => 'Absen masuk hari ini belum tercatat atau absen pulang sudah dilakukan.',
            ], 409);
        }

        return response()->json(['data' => $attendance]);
    }

    /**
     * @return array{latitude: int|float|string|null, longitude: int|float|string|null}
     */
    private function validateLocation(
        Request $request,
        ?WorkLocation $workLocation,
        ?WorkAssignment $assignment,
    ): array {
        if ($assignment?->is_online) {
            $request->validate([
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            ]);

            return ['latitude' => null, 'longitude' => null];
        }

        $location = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($workLocation) {
            $distance = $this->distanceInMeters(
                (float) $location['latitude'],
                (float) $location['longitude'],
                (float) $workLocation->latitude,
                (float) $workLocation->longitude,
            );

            if ($distance > $workLocation->radius_meters) {
                throw ValidationException::withMessages([
                    'latitude' => "Kamu berada di luar radius absensi {$workLocation->name} ({$workLocation->radius_meters} meter).",
                ]);
            }
        }

        return [
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
        ];
    }

    /**
     * @return array{location: ?WorkLocation, assignment: ?WorkAssignment}
     */
    private function resolveWorkContext(User $user, bool $requireLocation = true): array
    {
        if (
            $requireLocation
            && Department::query()->where('is_active', true)->exists()
            && (! $user->department_id || ! $user->department()->where('is_active', true)->exists())
        ) {
            throw new HttpResponseException(response()->json([
                'message' => 'Departemen kerja belum ditentukan atau sudah tidak aktif. Hubungi administrator.',
            ], 409));
        }

        $assignments = $user->workAssignments()
            ->with('workLocation')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->get();

        if ($assignments->count() > 1) {
            throw new HttpResponseException(response()->json([
                'message' => 'Terdapat lebih dari satu penugasan aktif. Hubungi administrator.',
            ], 409));
        }

        $assignment = $assignments->first();
        $location = $assignment
            ? $assignment->workLocation
            : $user->baseLocation;

        if ($assignment?->is_online) {
            return ['location' => null, 'assignment' => $assignment];
        }

        if (! $location && ! $requireLocation) {
            return ['location' => null, 'assignment' => $assignment];
        }

        if (! $location) {
            throw new HttpResponseException(response()->json([
                'message' => 'Cabang kerja belum ditentukan. Hubungi administrator sebelum melakukan absensi.',
            ], 409));
        }

        if (! $location->is_active || ! $location->geofenceEnabled()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Lokasi kerja belum aktif atau koordinat GPS-nya belum lengkap. Hubungi administrator.',
            ], 409));
        }

        return ['location' => $location, 'assignment' => $assignment];
    }

    private function distanceInMeters(float $latitude, float $longitude, float $officeLatitude, float $officeLongitude): float
    {
        $earthRadius = 6_371_000;
        $latitudeDelta = deg2rad($officeLatitude - $latitude);
        $longitudeDelta = deg2rad($officeLongitude - $longitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($officeLatitude))
            * sin($longitudeDelta / 2) ** 2;
        $a = min(1, max(0, $a));

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function selfieHash(Request $request): ?string
    {
        $selfie = $request->file('selfie');
        if (! $selfie) {
            return null;
        }

        $hash = hash_file('sha256', $selfie->getRealPath());
        if ($hash === false) {
            throw new \RuntimeException('Selfie absensi gagal diverifikasi.');
        }

        return $hash;
    }

    private function approvedAbsenceForToday(User $user): ?LeaveRequest
    {
        return $user->leaveRequests()
            ->where('status', 'approved')
            ->whereIn('type', ['izin', 'sakit', 'cuti'])
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->orderByDesc('start_date')
            ->first();
    }

    private function ensureNoApprovedAbsence(User $user): void
    {
        $absence = $this->approvedAbsenceForToday($user);
        if (! $absence) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => $this->absenceBlockMessage($absence),
            'code' => 'approved_absence',
        ], 409));
    }

    private function absenceBlockMessage(LeaveRequest $absence): string
    {
        $type = match ($absence->type) {
            'izin' => 'izin',
            'sakit' => 'sakit',
            'cuti' => 'cuti',
            default => 'ketidakhadiran',
        };

        return "Absensi tidak dapat dilakukan karena pengajuan {$type} Anda telah disetujui untuk hari ini.";
    }

    private function hasSelfie(?string $path): bool
    {
        return $path !== null && Storage::disk('local')->exists($path);
    }
}
