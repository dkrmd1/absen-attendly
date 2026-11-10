<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkShift;
use App\Notifications\AttendanceWorkflowNotification;
use App\Services\AttendanceNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_check_in_notifies_the_direct_department_supervisor(): void
    {
        $supervisor = User::factory()->create(['role' => 'division_head']);
        $department = Department::query()->create(['name' => 'Operations']);
        $department->supervisor_id = $supervisor->id;
        $department->save();
        $employee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);
        $attendance = $employee->attendances()->create([
            'department_id' => $department->id,
            'work_date' => '2026-11-09',
            'check_in_at' => '2026-11-09 09:20:00',
            'shift_name' => 'Pagi',
            'late_minutes' => 10,
        ]);

        app(AttendanceNotificationService::class)->notifyLateCheckIn($attendance);

        $notification = $supervisor->notifications()->firstOrFail();
        $this->assertSame(AttendanceWorkflowNotification::class, $notification->type);
        $this->assertSame('attendance_late_check_in', $notification->data['data']['kind']);
        $this->assertStringContainsString('10 menit', $notification->data['message']);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_missing_check_in_digest_waits_for_grace_and_skips_leave_and_non_workdays(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 09:24:00', config('app.timezone')));

        $supervisor = User::factory()->create(['role' => 'division_head']);
        $department = Department::query()->create(['name' => 'Operations']);
        $department->supervisor_id = $supervisor->id;
        $department->save();
        $shift = WorkShift::query()->create([
            'name' => 'Pagi',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'grace_period_minutes' => 10,
            'work_days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);

        $firstAbsent = $this->employee($department, $shift);
        $secondAbsent = $this->employee($department, $shift);
        $onLeave = $this->employee($department, $shift);
        $onLeave->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => '2026-11-09',
            'end_date' => '2026-11-09',
            'reason' => 'Cuti yang sudah disetujui',
            'status' => 'approved',
        ]);
        $alreadyCheckedIn = $this->employee($department, $shift);
        $alreadyCheckedIn->attendances()->create([
            'department_id' => $department->id,
            'work_shift_id' => $shift->id,
            'work_date' => '2026-11-09',
            'check_in_at' => '2026-11-09 08:55:00',
        ]);
        $offDayShift = WorkShift::query()->create([
            'name' => 'Selasa',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'grace_period_minutes' => 0,
            'work_days' => [2],
            'is_active' => true,
        ]);
        $this->employee($department, $offDayShift);

        $this->artisan('attendly:notify-missing-attendance')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 0);

        $this->travelTo(Carbon::parse('2026-11-09 09:25:00', config('app.timezone')));
        $this->artisan('attendly:notify-missing-attendance')->assertSuccessful();

        $notification = $supervisor->notifications()->firstOrFail();
        $this->assertSame('attendance_missing_check_in', $notification->data['data']['kind']);
        $this->assertSame(
            [$firstAbsent->id, $secondAbsent->id],
            $notification->data['data']['employee_ids'],
        );
        $this->assertSame(0, app(AttendanceNotificationService::class)->notifyMissingCheckIns());
        $this->assertDatabaseCount('notifications', 1);
    }

    private function employee(Department $department, WorkShift $shift): User
    {
        return User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
            'work_shift_id' => $shift->id,
        ]);
    }
}
