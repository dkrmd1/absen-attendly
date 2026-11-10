<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\PayrollRecord;
use App\Models\TaxDocument;
use App\Models\User;
use App\Models\WorkAssignment;
use App\Models\WorkGroup;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use App\Services\GooglePlayIntegrityDecoder;
use App\Services\PlayIntegrityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AttendlyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_bearer_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create([
            'email' => 'rizky@example.test',
            'password' => 'correct-password',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'rizky@example.test',
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer');

        $token = $login->json('access_token');
        $this->assertIsString($token);

        $this->withToken($token)->getJson('/api/me')->assertOk();
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_profile_includes_department_and_base_work_location(): void
    {
        $department = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $location = $this->createWorkLocation(['name' => 'Cabang Bandung']);
        $employee = User::factory()->create([
            'department_id' => $department->id,
            'base_location_id' => $location->id,
        ]);
        $token = $employee->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.department', 'Operasional')
            ->assertJsonPath('data.base_location', 'Cabang Bandung');
    }

    public function test_employee_can_update_own_profile_and_securely_store_its_documents(): void
    {
        Storage::fake('local');
        $employee = User::factory()->create();
        $token = $employee->createToken('test')->plainTextToken;
        $npwp = UploadedFile::fake()->create('npwp.pdf', 24, 'application/pdf');
        $photo = UploadedFile::fake()->image('profile.jpg');

        $response = $this->withToken($token)->post('/api/profile', [
            'name' => 'Rizky Aditya',
            'nik' => '3201010101010001',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 10, Bandung',
            'npwp_document' => $npwp,
            'profile_photo' => $photo,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rizky Aditya')
            ->assertJsonPath('data.nik', '3201010101010001')
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.address', 'Jl. Merdeka No. 10, Bandung')
            ->assertJsonPath('data.has_npwp_document', true)
            ->assertJsonPath('data.has_profile_photo', true);

        $employee->refresh();
        Storage::disk('local')->assertExists($employee->npwp_document_path);
        Storage::disk('local')->assertExists($employee->profile_photo_path);
        $this->withToken($token)->get('/api/profile/npwp')->assertOk();
        $this->withToken($token)->get('/api/profile/photo')->assertOk();

        $this->app['auth']->forgetGuards();
        $otherEmployee = User::factory()->create();
        $otherToken = $otherEmployee->createToken('other')->plainTextToken;
        $this->withToken($otherToken)->getJson('/api/profile/npwp')->assertNotFound();
        $this->withToken($otherToken)->getJson('/api/profile/photo')->assertNotFound();
        $this->assertSame(
            '3201010101010001',
            $response->json('data.nik'),
        );
    }

    public function test_employee_profile_validates_nik_and_requires_authentication(): void
    {
        $this->postJson('/api/profile', ['name' => 'Rizky'])->assertUnauthorized();

        $employee = User::factory()->create();
        $token = $employee->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/profile', [
            'name' => 'Rizky Aditya',
            'nik' => '123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('nik');
    }

    public function test_attendance_requires_authentication_and_enforces_one_check_in_per_day(): void
    {
        $this->postJson('/api/attendance/check-in')->assertUnauthorized();

        $location = $this->createWorkLocation();
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.check_in_latitude', '-6.2000000');

        $this->withToken($token)->postJson('/api/attendance/check-in')
            ->assertConflict();

        $this->withToken($token)->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id);
    }

    public function test_attendance_correction_requires_manager_approval_then_hr_application(): void
    {
        $department = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $divisionHead = User::factory()->create([
            'role' => 'division_head',
            'department_id' => $department->id,
        ]);
        $department->update(['supervisor_id' => $divisionHead->id]);
        $leader = User::factory()->create([
            'role' => 'group_lead',
            'department_id' => $department->id,
        ]);
        $group = WorkGroup::query()->create([
            'department_id' => $department->id,
            'name' => 'Layanan',
            'leader_id' => $leader->id,
        ]);
        $leader->update(['work_group_id' => $group->id]);
        $employee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
            'work_group_id' => $group->id,
        ]);
        $hr = User::factory()->create(['role' => 'hr']);
        $workDate = today()->subDay();
        $attendance = $employee->attendances()->create([
            'work_date' => $workDate,
            'check_in_at' => $workDate->copy()->setTime(8, 0),
            'check_out_at' => $workDate->copy()->setTime(17, 0),
            'department_id' => $department->id,
        ]);
        $employeeToken = $employee->createToken('employee')->plainTextToken;
        $newCheckIn = $workDate->copy()->setTime(8, 15);
        $newCheckOut = $workDate->copy()->setTime(17, 15);

        $correction = $this->withToken($employeeToken)
            ->postJson("/api/attendances/{$attendance->id}/corrections", [
                'check_in_at' => $newCheckIn->toIso8601String(),
                'check_out_at' => $newCheckOut->toIso8601String(),
                'reason' => 'Mesin absensi terlambat membaca kartu.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_group')
            ->json('data');
        $this->assertSame(1, $leader->notifications()->count());

        $correctionId = $correction['id'];
        $this->actingAs($leader)
            ->get(route('admin.attendance-corrections.index'))
            ->assertOk()
            ->assertSee('Koreksi absensi')
            ->assertSee('Mesin absensi terlambat membaca kartu.');
        $this->actingAs($leader)
            ->patch(route('admin.attendance-corrections.update', $correctionId), [
                'decision' => 'approved',
            ])
            ->assertRedirect(route('admin.attendance-corrections.index'));
        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correctionId,
            'status' => 'pending_division',
        ]);

        $this->actingAs($divisionHead)
            ->patch(route('admin.attendance-corrections.update', $correctionId), [
                'decision' => 'approved',
            ])
            ->assertRedirect(route('admin.attendance-corrections.index'));
        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correctionId,
            'status' => 'pending_hr',
        ]);

        $this->actingAs($hr)
            ->patch(route('admin.attendance-corrections.update', $correctionId), [
                'decision' => 'applied',
                'note' => 'Diverifikasi dengan laporan mesin.',
            ])
            ->assertRedirect(route('admin.attendance-corrections.index'));
        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correctionId,
            'status' => 'applied',
            'applied_by' => $hr->id,
        ]);
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'check_in_at' => $newCheckIn->toDateTimeString(),
            'check_out_at' => $newCheckOut->toDateTimeString(),
        ]);
        $this->assertDatabaseCount('attendance_correction_reviews', 3);
        $this->app['auth']->forgetGuards();
        $this->withToken($employeeToken)->getJson('/api/attendance-corrections')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'applied')
            ->assertJsonCount(3, 'data.0.reviews');
    }

    public function test_rejected_attendance_correction_is_audited_without_changing_attendance(): void
    {
        $department = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $divisionHead = User::factory()->create([
            'role' => 'division_head',
            'department_id' => $department->id,
        ]);
        $department->update(['supervisor_id' => $divisionHead->id]);
        $employee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);
        User::factory()->create(['role' => 'hr']);
        $workDate = today()->subDay();
        $originalCheckIn = $workDate->copy()->setTime(8, 0);
        $attendance = $employee->attendances()->create([
            'work_date' => $workDate,
            'check_in_at' => $originalCheckIn,
            'department_id' => $department->id,
        ]);
        $token = $employee->createToken('employee')->plainTextToken;
        $correction = $this->withToken($token)
            ->postJson("/api/attendances/{$attendance->id}/corrections", [
                'check_in_at' => $workDate->copy()->setTime(8, 15)->toIso8601String(),
                'reason' => 'Mesin absensi mengalami gangguan.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_division')
            ->json('data');

        $this->actingAs($divisionHead)
            ->patch(route('admin.attendance-corrections.update', $correction['id']), [
                'decision' => 'rejected',
                'note' => 'Tidak ditemukan bukti gangguan mesin.',
            ])
            ->assertRedirect(route('admin.attendance-corrections.index'));

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $correction['id'],
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('attendance_correction_reviews', [
            'correction_request_id' => $correction['id'],
            'reviewer_id' => $divisionHead->id,
            'stage' => 'division',
            'decision' => 'rejected',
            'note' => 'Tidak ditemukan bukti gangguan mesin.',
        ]);
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'check_in_at' => $originalCheckIn->toDateTimeString(),
        ]);
        $this->assertDatabaseCount('attendance_correction_requests', 1);
    }

    public function test_attendance_settings_expose_only_the_assigned_work_location_coordinates(): void
    {
        $location = $this->createWorkLocation([
            'latitude' => -6.21,
            'longitude' => 106.81,
        ]);
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.office_name', 'Kantor Pusat')
            ->assertJsonPath('data.office_latitude', -6.21)
            ->assertJsonPath('data.office_longitude', 106.81);

    }

    public function test_attendance_settings_do_not_expose_coordinates_without_an_assigned_location(): void
    {
        $unassigned = User::factory()->create(['base_location_id' => null]);
        $unassignedToken = $unassigned->createToken('test')->plainTextToken;

        $this->withToken($unassignedToken)->getJson('/api/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.office_latitude', null)
            ->assertJsonPath('data.office_longitude', null);
    }

    public function test_check_in_stores_selfie_privately_and_marks_it_submitted(): void
    {
        Storage::fake('local');
        $location = $this->createWorkLocation();
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->post('/api/attendance/check-in', [
            'latitude' => -6.2,
            'longitude' => 106.8,
            'selfie' => UploadedFile::fake()->image('check-in.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.selfie_review_status', 'submitted')
            ->assertJsonMissingPath('data.check_in_selfie_path');

        $attendance = $user->attendances()->firstOrFail();
        $this->assertNotNull($attendance->check_in_selfie_path);
        Storage::disk('local')->assertExists($attendance->check_in_selfie_path);
    }

    public function test_employee_can_only_view_their_own_attendance_selfies(): void
    {
        Storage::fake('local');
        $employee = User::factory()->create();
        $otherEmployee = User::factory()->create();
        $workDate = today()->subDay();
        $checkInPath = UploadedFile::fake()
            ->image('check-in.jpg')
            ->store('attendance-selfies', 'local');
        $checkOutPath = UploadedFile::fake()
            ->image('check-out.jpg')
            ->store('attendance-selfies', 'local');
        $attendance = $employee->attendances()->create([
            'work_date' => $workDate,
            'check_in_at' => $workDate->copy()->setTime(8, 0),
            'check_out_at' => $workDate->copy()->setTime(17, 0),
            'check_in_selfie_path' => $checkInPath,
            'check_out_selfie_path' => $checkOutPath,
        ]);
        $employeeToken = $employee->createToken('test')->plainTextToken;
        $otherToken = $otherEmployee->createToken('test')->plainTextToken;

        $this->withToken($employeeToken)->getJson('/api/attendances')
            ->assertOk()
            ->assertJsonPath('data.0.has_check_in_selfie', true)
            ->assertJsonPath('data.0.has_check_out_selfie', true)
            ->assertJsonMissingPath('data.0.check_in_selfie_path')
            ->assertJsonMissingPath('data.0.check_out_selfie_path');

        $checkInResponse = $this->withToken($employeeToken)
            ->get("/api/attendances/{$attendance->id}/selfies/check-in");
        $checkInResponse->assertOk();
        $this->assertSame('image/jpeg', $checkInResponse->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'no-store',
            $checkInResponse->headers->get('Cache-Control'),
        );

        $this->withToken($employeeToken)
            ->get("/api/attendances/{$attendance->id}/selfies/check-out")
            ->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)
            ->get("/api/attendances/{$attendance->id}/selfies/check-in")
            ->assertNotFound();

        $this->app['auth']->forgetGuards();
        $attendanceWithoutSelfie = $employee->attendances()->create([
            'work_date' => $workDate->copy()->subDay(),
            'check_in_at' => $workDate->copy()->subDay()->setTime(8, 0),
        ]);
        $this->withToken($employeeToken)
            ->get("/api/attendances/{$attendanceWithoutSelfie->id}/selfies/check-in")
            ->assertNotFound();
    }

    public function test_play_integrity_is_required_and_verified_before_check_in(): void
    {
        config(['play_integrity.enabled' => true]);
        $location = $this->createWorkLocation();
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $token = $user->createToken('test')->plainTextToken;
        $selfie = UploadedFile::fake()->image('check-in.jpg');
        $expectedHash = PlayIntegrityVerifier::requestHash(
            $user->id,
            'check-in',
            '-6.2000000',
            '106.8000000',
            hash_file('sha256', $selfie->getRealPath()),
        );
        $decoder = Mockery::mock(GooglePlayIntegrityDecoder::class);
        $decoder->shouldReceive('decode')
            ->once()
            ->with('valid-integrity-token')
            ->andReturn([
                'requestDetails' => [
                    'requestPackageName' => 'id.co.attendly.mobile',
                    'requestHash' => $expectedHash,
                    'timestampMillis' => (string) ((int) (microtime(true) * 1000)),
                ],
                'appIntegrity' => ['appRecognitionVerdict' => 'PLAY_RECOGNIZED'],
                'deviceIntegrity' => [
                    'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
                ],
            ]);
        $this->app->instance(GooglePlayIntegrityDecoder::class, $decoder);

        $this->withToken($token)->getJson('/api/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.play_integrity_required', true);

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Token integritas perangkat tidak tersedia.');

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ], [
            'X-Play-Integrity' => 'valid-integrity-token',
        ], $selfie)->assertCreated();
    }

    public function test_play_integrity_rejects_a_verdict_bound_to_a_different_request(): void
    {
        config(['play_integrity.enabled' => true]);
        $location = $this->createWorkLocation();
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $token = $user->createToken('test')->plainTextToken;
        $decoder = Mockery::mock(GooglePlayIntegrityDecoder::class);
        $decoder->shouldReceive('decode')
            ->once()
            ->andReturn([
                'requestDetails' => [
                    'requestPackageName' => 'id.co.attendly.mobile',
                    'requestHash' => 'different-request-hash',
                    'timestampMillis' => (string) ((int) (microtime(true) * 1000)),
                ],
                'appIntegrity' => ['appRecognitionVerdict' => 'PLAY_RECOGNIZED'],
                'deviceIntegrity' => [
                    'deviceRecognitionVerdict' => ['MEETS_DEVICE_INTEGRITY'],
                ],
            ]);
        $this->app->instance(GooglePlayIntegrityDecoder::class, $decoder);

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ], [
            'X-Play-Integrity' => 'misbound-integrity-token',
        ])->assertForbidden()
            ->assertJsonPath(
                'message',
                'Token integritas perangkat tidak valid atau sudah kedaluwarsa.',
            );

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_check_in_without_selfie_is_rejected(): void
    {
        $location = $this->createWorkLocation();
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/attendance/check-in', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('selfie');
    }

    public function test_approved_leave_blocks_check_in_and_check_out_for_covered_dates(): void
    {
        $location = $this->createWorkLocation();
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $user->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => today()->subDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Cuti tahunan',
            'requester_role' => 'employee',
            'status' => 'approved',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.attendance_blocked', true)
            ->assertJsonPath(
                'data.attendance_block_reason',
                'Absensi tidak dapat dilakukan karena pengajuan cuti Anda telah disetujui untuk hari ini.',
            );

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertConflict()
            ->assertJsonPath('code', 'approved_absence');
        $this->assertDatabaseCount('attendances', 0);

        $user->attendances()->create([
            'work_date' => today(),
            'check_in_at' => now()->subHours(8),
        ]);
        $this->checkOut($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertConflict()
            ->assertJsonPath('code', 'approved_absence');
        $this->assertNull($user->attendances()->firstOrFail()->check_out_at);
    }

    public function test_pending_permission_does_not_block_attendance(): void
    {
        $location = $this->createWorkLocation();
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $user->leaveRequests()->create([
            'type' => 'izin',
            'start_date' => today(),
            'end_date' => today(),
            'reason' => 'Keperluan keluarga',
            'requester_role' => 'employee',
            'status' => 'pending_group',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.attendance_blocked', false)
            ->assertJsonPath('data.attendance_block_reason', null);

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertCreated();
    }

    public function test_check_out_requires_check_in_and_can_only_be_recorded_once(): void
    {
        Storage::fake('local');
        $location = $this->createWorkLocation();
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/attendance/check-out')
            ->assertConflict();

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])
            ->assertCreated();

        $this->withToken($token)->postJson('/api/attendance/check-out', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('selfie');

        $this->checkOut($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])
            ->assertOk()
            ->assertJsonPath('data.check_out_at', fn ($value) => $value !== null)
            ->assertJsonMissingPath('data.check_out_selfie_path');
        $attendance = $user->attendances()->firstOrFail();
        $this->assertNotNull($attendance->check_out_selfie_path);
        Storage::disk('local')->assertExists($attendance->check_out_selfie_path);

        $this->withToken($token)->postJson('/api/attendance/check-out', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])
            ->assertConflict();
    }

    public function test_configured_geofence_requires_location_and_rejects_check_in_outside_radius(): void
    {
        $location = $this->createWorkLocation([
            'name' => 'Kantor Pusat',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meters' => 100,
        ]);
        $user = User::factory()->create(['role' => 'employee', 'base_location_id' => $location->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/attendance/check-in')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->withToken($token)->postJson('/api/attendance/check-in', [
            'latitude' => -6.21,
            'longitude' => 106.8,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->checkIn($token, [
            'latitude' => -6.20001,
            'longitude' => 106.80001,
        ])->assertCreated();

        $this->checkOut($token, [
            'latitude' => -6.21,
            'longitude' => 106.8,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->checkOut($token, [
            'latitude' => -6.20001,
            'longitude' => 106.80001,
        ])->assertOk();
    }

    public function test_active_approved_assignment_takes_priority_over_employee_base_location(): void
    {
        $baseLocation = $this->createWorkLocation(['name' => 'Cabang Utama']);
        $temporaryLocation = $this->createWorkLocation([
            'name' => 'Cabang Tugas',
            'latitude' => -6.3,
            'longitude' => 106.9,
        ]);
        $user = User::factory()->create(['base_location_id' => $baseLocation->id]);
        $leaveRequest = $user->leaveRequests()->create([
            'type' => 'perjalanan_dinas',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Penugasan luar kota',
            'status' => 'approved',
        ]);
        WorkAssignment::query()->create([
            'user_id' => $user->id,
            'work_location_id' => $temporaryLocation->id,
            'leave_request_id' => $leaveRequest->id,
            'type' => 'perjalanan_dinas',
            'start_date' => today(),
            'end_date' => today(),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.office_name', 'Cabang Tugas');

        $this->checkIn($token, [
            'latitude' => -6.3,
            'longitude' => 106.9,
        ])->assertCreated()
            ->assertJsonPath('data.work_location_id', $temporaryLocation->id)
            ->assertJsonPath('data.work_assignment_id', fn ($value) => $value !== null);
    }

    public function test_employee_without_a_location_cannot_check_in(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.location_assigned', false);

        $this->withToken($token)->postJson('/api/attendance/check-in')
            ->assertConflict()
            ->assertJsonPath('message', 'Cabang kerja belum ditentukan. Hubungi administrator sebelum melakukan absensi.');
    }

    public function test_check_in_calculates_lateness_after_shift_grace_and_saves_shift_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:15:00'));

        $location = $this->createWorkLocation();
        $shift = WorkShift::query()->create([
            'name' => 'Shift Reguler',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'grace_period_minutes' => 10,
            'work_days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'employee',
            'base_location_id' => $location->id,
            'work_shift_id' => $shift->id,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.shift.name', 'Shift Reguler')
            ->assertJsonPath('data.shift.start_time', '08:00')
            ->assertJsonPath('data.shift.grace_period_minutes', 10)
            ->assertJsonPath('data.shift.work_days.0', 1);

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertCreated()
            ->assertJsonPath('data.shift_name', 'Shift Reguler')
            ->assertJsonPath('data.is_scheduled_workday', true)
            ->assertJsonPath('data.late_minutes', 5);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_shift_id' => $shift->id,
            'shift_name' => 'Shift Reguler',
            'shift_start_time' => '08:00',
            'shift_grace_period_minutes' => 10,
            'is_scheduled_workday' => 1,
            'late_minutes' => 5,
        ]);

        Carbon::setTestNow();
    }

    public function test_check_in_within_shift_grace_period_is_on_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:10:00'));

        $location = $this->createWorkLocation();
        $shift = WorkShift::query()->create([
            'name' => 'Shift Toleransi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'grace_period_minutes' => 10,
            'work_days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'employee',
            'base_location_id' => $location->id,
            'work_shift_id' => $shift->id,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertCreated()
            ->assertJsonPath('data.late_minutes', 0);

        Carbon::setTestNow();
    }

    public function test_check_in_marks_a_shift_day_off_without_counting_it_as_late(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-03 08:30:00'));

        $location = $this->createWorkLocation();
        $shift = WorkShift::query()->create([
            'name' => 'Shift Senin',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'grace_period_minutes' => 0,
            'work_days' => [1],
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'employee',
            'base_location_id' => $location->id,
            'work_shift_id' => $shift->id,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->checkIn($token, [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertCreated()
            ->assertJsonPath('data.is_scheduled_workday', false)
            ->assertJsonPath('data.late_minutes', null);

        Carbon::setTestNow();
    }

    public function test_employee_must_be_assigned_to_a_department_when_departments_are_configured(): void
    {
        Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $location = $this->createWorkLocation();
        $user = User::factory()->create(['base_location_id' => $location->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/attendance/check-in', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertConflict()
            ->assertJsonPath('message', 'Departemen kerja belum ditentukan atau sudah tidak aktif. Hubungi administrator.');
    }

    public function test_approved_online_training_attendance_does_not_require_gps_and_waits_for_review(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $leaveRequest = $user->leaveRequests()->create([
            'type' => 'diklat',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Diklat daring',
            'status' => 'approved',
        ]);
        $assignment = WorkAssignment::query()->create([
            'user_id' => $user->id,
            'leave_request_id' => $leaveRequest->id,
            'type' => 'diklat',
            'start_date' => today(),
            'end_date' => today(),
            'is_online' => true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.online_training', true)
            ->assertJsonPath('data.requires_location', false);

        $this->checkIn($token)
            ->assertCreated()
            ->assertJsonPath('data.attendance_mode', 'online_training')
            ->assertJsonPath('data.verification_status', 'pending_review')
            ->assertJsonPath('data.work_assignment_id', $assignment->id)
            ->assertJsonPath('data.check_in_latitude', null);
    }

    public function test_employee_can_only_see_their_own_attendance_and_submit_leave_request(): void
    {
        $employee = User::factory()->create();
        $otherEmployee = User::factory()->create();
        $token = $employee->createToken('test')->plainTextToken;

        $otherEmployee->attendances()->create([
            'work_date' => today()->subDay(),
            'check_in_at' => now()->subDay()->setTime(8, 0),
        ]);

        $this->withToken($token)->getJson('/api/attendances')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'cuti',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-11',
            'reason' => 'Keperluan keluarga',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $employee->id)
            ->assertJsonPath('data.status', 'pending_hr');
    }

    public function test_leave_request_rejects_an_end_date_before_its_start_date(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'izin',
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-11',
            'reason' => 'Keperluan pribadi',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_employee_can_submit_business_travel_and_training_requests(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'perjalanan_dinas',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'reason' => 'Kunjungan kantor cabang',
            'details' => ['destination' => 'Bandung'],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'perjalanan_dinas')
            ->assertJsonPath('data.details.destination', 'Bandung');

        $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'diklat',
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-16',
            'reason' => 'Pelatihan pengembangan kompetensi',
            'details' => ['institution' => 'Pusat Pelatihan Attendly'],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'diklat')
            ->assertJsonPath('data.details.institution', 'Pusat Pelatihan Attendly');
    }

    public function test_business_travel_and_training_require_their_specific_details(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;
        $payload = [
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'reason' => 'Kegiatan kerja',
        ];

        $this->withToken($token)->postJson('/api/leave-requests', [
            ...$payload,
            'type' => 'perjalanan_dinas',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('details.destination');

        $this->withToken($token)->postJson('/api/leave-requests', [
            ...$payload,
            'type' => 'diklat',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('details.institution');
    }

    public function test_sick_leave_categories_require_the_doctors_note_when_selected(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;
        Storage::fake('local');

        $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'sakit',
            'subtype' => 'sakit_dengan_surat',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Periksa dokter',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');

        $response = $this->withToken($token)->post('/api/leave-requests', [
            'type' => 'sakit',
            'subtype' => 'sakit_dengan_surat',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Periksa dokter',
            'attachment' => UploadedFile::fake()->create('surat-dokter.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.subtype', 'sakit_dengan_surat')
            ->assertJsonPath('data.attachment_available', true)
            ->assertJsonMissingPath('data.attachment_path');
        $record = $user->leaveRequests()->firstOrFail();
        Storage::disk('local')->assertExists($record->attachment_path);
        $this->withToken($token)->get('/api/leave-requests/'.$record->id.'/attachment')->assertOk();

        $anotherEmployee = User::factory()->create(['role' => 'employee']);
        $this->app['auth']->forgetGuards();
        $this->withToken($anotherEmployee->createToken('test')->plainTextToken)
            ->get('/api/leave-requests/'.$record->id.'/attachment')
            ->assertNotFound();
    }

    public function test_employee_can_submit_overtime_with_work_hours(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'lembur',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Penyelesaian laporan',
            'details' => [
                'start_time' => '18:00',
                'end_time' => '20:00',
                'work_description' => 'Menuntaskan laporan bulanan',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'lembur')
            ->assertJsonPath('data.details.end_time', '20:00');
    }

    public function test_employee_resources_are_private_and_only_published_news_is_visible(): void
    {
        Storage::fake('local');
        $employee = User::factory()->create(['role' => 'employee']);
        $anotherEmployee = User::factory()->create(['role' => 'employee']);
        $token = $employee->createToken('test')->plainTextToken;
        $otherToken = $anotherEmployee->createToken('test')->plainTextToken;
        $payslipPath = UploadedFile::fake()->create('payslip.pdf', 10, 'application/pdf')
            ->store('private/payslips', 'local');
        $payroll = PayrollRecord::query()->create([
            'user_id' => $employee->id,
            'period_start' => today()->startOfMonth()->toDateString(),
            'gross_amount' => 10000000,
            'deductions_amount' => 1000000,
            'net_amount' => 9000000,
            'payslip_path' => $payslipPath,
        ]);
        $taxPath = UploadedFile::fake()->create('tax.pdf', 10, 'application/pdf')
            ->store('private/tax-documents', 'local');
        $taxDocument = TaxDocument::query()->create([
            'user_id' => $employee->id,
            'tax_year' => today()->year,
            'title' => 'Bukti Potong',
            'file_path' => $taxPath,
            'visible_at' => now(),
        ]);
        Announcement::query()->create([
            'category' => 'berita',
            'title' => 'Informasi terbit',
            'body' => 'Konten publik karyawan',
            'published_at' => now()->subMinute(),
        ]);
        Announcement::query()->create([
            'category' => 'pengumuman',
            'title' => 'Informasi draft',
            'body' => 'Belum diterbitkan',
        ]);

        $this->withToken($token)->getJson('/api/payroll')
            ->assertOk()
            ->assertJsonPath('data.0.net_amount', '9000000.00');
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/payroll')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->get('/api/payroll/'.$payroll->id.'/payslip')
            ->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->get('/api/payroll/'.$payroll->id.'/payslip')
            ->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/tax-documents')
            ->assertOk()
            ->assertJsonPath('data.0.id', $taxDocument->id);
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->get('/api/tax-documents/'.$taxDocument->id.'/file')
            ->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Informasi terbit');
    }

    public function test_leave_balance_uses_only_approved_annual_leave_workdays_and_is_private(): void
    {
        $shift = WorkShift::query()->create([
            'name' => 'Cuti API Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'grace_period_minutes' => 0,
            'work_days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);
        $employee = User::factory()->create([
            'role' => 'employee',
            'work_shift_id' => $shift->id,
        ]);
        $anotherEmployee = User::factory()->create(['role' => 'employee']);
        $year = now()->year;
        $startDate = Carbon::create($year, 6, 1)->startOfWeek(Carbon::MONDAY);
        $endDate = $startDate->copy()->addDays(4);
        $holidayDate = $startDate->copy()->addDays(2);
        LeaveBalance::query()->create([
            'user_id' => $employee->id,
            'year' => $year,
            'entitlement_days' => 12,
        ]);
        CompanyHoliday::query()->create([
            'date' => $holidayDate->toDateString(),
            'name' => 'Libur perusahaan',
        ]);
        $employee->leaveRequests()->create([
            'type' => 'cuti',
            'subtype' => 'cuti_tahunan',
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'reason' => 'Libur keluarga',
            'status' => 'approved',
        ]);
        $employee->leaveRequests()->create([
            'type' => 'cuti',
            'subtype' => 'cuti_tahunan',
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'reason' => 'Belum disetujui',
            'status' => 'pending_hr',
        ]);
        $employee->leaveRequests()->create([
            'type' => 'cuti',
            'subtype' => 'cuti_menikah',
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'reason' => 'Cuti khusus',
            'status' => 'approved',
        ]);

        $this->withToken($employee->createToken('test')->plainTextToken)
            ->getJson('/api/leave-balance')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.entitlement_days', 12)
            ->assertJsonPath('data.used_days', 4)
            ->assertJsonPath('data.remaining_days', 8);

        $this->app['auth']->forgetGuards();
        $this->withToken($anotherEmployee->createToken('test')->plainTextToken)
            ->getJson('/api/leave-balance')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.entitlement_days', null);
    }

    public function test_employee_can_submit_and_view_daily_activity_reports(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/activity-reports', [
            'activity_date' => today()->toDateString(),
            'title' => 'Rekonsiliasi',
            'description' => 'Menyelesaikan rekonsiliasi data.',
            'hours' => 2.5,
        ])->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'pending');

        $this->withToken($token)->getJson('/api/activity-reports')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Rekonsiliasi');
        $this->withToken($token)->postJson('/api/activity-reports', [
            'activity_date' => today()->addDay()->toDateString(),
            'title' => 'Tanggal masa depan',
            'description' => 'Tidak boleh dikirim.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('activity_date');
    }

    public function test_employee_account_command_hides_and_hashes_the_password(): void
    {
        $this->artisan('attendly:create-employee')
            ->expectsQuestion('Nama karyawan', 'Rizky Aditya')
            ->expectsQuestion('Email', 'Rizky@example.test')
            ->expectsQuestion('Kata sandi (minimal 12 karakter)', 'safe-test-password')
            ->expectsQuestion('Ulangi kata sandi', 'safe-test-password')
            ->expectsOutputToContain('Akun karyawan berhasil dibuat.')
            ->doesntExpectOutputToContain('safe-test-password')
            ->assertSuccessful();

        $employee = User::query()->where('email', 'rizky@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('safe-test-password', $employee->password));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createWorkLocation(array $attributes = []): WorkLocation
    {
        return WorkLocation::query()->create(array_merge([
            'name' => 'Kantor Pusat',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meters' => 100,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $headers
     */
    private function checkIn(
        string $token,
        array $attributes = [],
        array $headers = [],
        ?UploadedFile $selfie = null,
    ): \Illuminate\Testing\TestResponse {
        return $this->withToken($token)->post('/api/attendance/check-in', [
            ...$attributes,
            'selfie' => $selfie ?? UploadedFile::fake()->image('check-in.jpg'),
        ], ['Accept' => 'application/json', ...$headers]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function checkOut(string $token, array $attributes = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)->post('/api/attendance/check-out', [
            ...$attributes,
            'selfie' => UploadedFile::fake()->image('check-out.jpg'),
        ], ['Accept' => 'application/json']);
    }
}
