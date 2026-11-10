<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestReview;
use App\Models\User;
use App\Models\WorkGroup;
use App\Services\LeaveApprovalWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_head_can_review_requests_from_the_mobile_api(): void
    {
        $department = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
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
            'department_id' => $department->id,
            'work_group_id' => $group->id,
        ]);
        $employeeToken = $employee->createToken('employee')->plainTextToken;
        $leaderToken = $leader->createToken('leader')->plainTextToken;
        $otherEmployee = User::factory()->create();
        $otherEmployeeToken = $otherEmployee->createToken('other')->plainTextToken;

        $leaveRequest = $this->withToken($employeeToken)
            ->postJson('/api/leave-requests', [
                'type' => 'izin',
                'start_date' => today()->addDay()->toDateString(),
                'end_date' => today()->addDay()->toDateString(),
                'reason' => 'Keperluan keluarga',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_group')
            ->json('data');
        $this->app['auth']->forgetGuards();

        $this->withToken($leaderToken)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.can_review_leave_requests', true);
        $this->app['auth']->forgetGuards();
        $this->withToken($otherEmployeeToken)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.can_review_leave_requests', false);
        $this->app['auth']->forgetGuards();

        $this->withToken($otherEmployeeToken)
            ->getJson('/api/leave-requests/review-queue')
            ->assertForbidden();
        $this->app['auth']->forgetGuards();

        $this->withToken($leaderToken)
            ->getJson('/api/leave-requests/review-queue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.applicant_name', $employee->name)
            ->assertJsonPath('data.0.review_stage', 'group')
            ->assertJsonPath('data.0.is_final_reviewer', true);
        $this->app['auth']->forgetGuards();

        $this->withToken($leaderToken)
            ->patchJson("/api/leave-requests/{$leaveRequest['id']}/review", [
                'status' => 'approved',
                'review_note' => 'Silakan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest['id'],
            'status' => 'approved',
            'group_status' => 'approved',
            'group_reviewed_by' => $leader->id,
        ]);
        $this->assertDatabaseHas('leave_request_reviews', [
            'leave_request_id' => $leaveRequest['id'],
            'reviewer_id' => $leader->id,
            'stage' => 'group',
            'decision' => 'approved',
        ]);
    }

    public function test_employee_request_is_finally_approved_by_its_group_head(): void
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
        $hr = User::factory()->create(['role' => 'hr']);
        $admin = User::factory()->create(['role' => 'admin']);
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
        $token = $employee->createToken('test')->plainTextToken;

        $request = $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'izin',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Keperluan keluarga',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_group')
            ->json('data');
        $leaveRequest = LeaveRequest::query()->findOrFail($request['id']);
        $this->assertSame($department->id, $leaveRequest->department_id);
        $this->assertSame($group->id, $leaveRequest->work_group_id);

        $otherLeader = User::factory()->create([
            'role' => 'group_lead',
            'department_id' => $department->id,
        ]);
        $otherGroup = WorkGroup::query()->create([
            'department_id' => $department->id,
            'name' => 'Keuangan',
            'leader_id' => $otherLeader->id,
        ]);
        $otherLeader->update(['work_group_id' => $otherGroup->id]);
        $this->actingAs($otherLeader)
            ->get(route('admin.leave-requests.index'))
            ->assertOk()
            ->assertDontSee($employee->email);
        $this->patch(route('admin.leave-requests.update', $leaveRequest), ['status' => 'approved'])
            ->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.update', $leaveRequest), ['status' => 'approved'])
            ->assertNotFound();

        $this->actingAs($leader)
            ->get(route('admin.leave-requests.index'))
            ->assertOk()
            ->assertSee($employee->email)
            ->assertSee('Tahap Anda: Group Head')
            ->assertSee('Setujui akhir');
        $this->actingAs($leader)
            ->patch(route('admin.leave-requests.update', $leaveRequest), ['status' => 'approved'])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'group_status' => 'approved',
            'group_reviewed_by' => $leader->id,
            'reviewed_by' => $leader->id,
        ]);
        $this->assertSame(
            ['group'],
            LeaveRequestReview::query()->where('leave_request_id', $leaveRequest->id)
                ->orderBy('id')->pluck('stage')->all(),
        );
    }

    public function test_employee_without_group_head_is_finally_approved_by_department_head(): void
    {
        $department = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $supervisor = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);
        $department->update(['supervisor_id' => $supervisor->id]);
        $employee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);
        $hr = User::factory()->create(['role' => 'hr']);
        $employeeToken = $employee->createToken('employee')->plainTextToken;

        $response = $this->withToken($employeeToken)->postJson('/api/leave-requests', [
            'type' => 'izin',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Keperluan keluarga',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $leaveRequest = LeaveRequest::query()->findOrFail($response->json('data.id'));
        $this->assertSame(1, $supervisor->unreadNotifications()->count());
        $this->assertSame(0, $hr->unreadNotifications()->count());

        $this->actingAs($supervisor)
            ->get(route('admin.leave-requests.index'))
            ->assertOk()
            ->assertSee($employee->email)
            ->assertSee('Tahap Anda: Pimpinan Divisi')
            ->assertSee('Setujui akhir');

        $this->actingAs($supervisor)
            ->patch(route('admin.leave-requests.update', $leaveRequest), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'supervisor_reviewed_by' => $supervisor->id,
            'reviewed_by' => $supervisor->id,
        ]);
        $this->assertSame(0, $hr->unreadNotifications()->count());
    }

    public function test_group_head_request_is_finally_approved_by_division_head(): void
    {
        $department = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $divisionHead = User::factory()->create([
            'role' => 'division_head',
            'department_id' => $department->id,
        ]);
        $director = User::factory()->create(['role' => 'director']);
        $department->update([
            'supervisor_id' => $divisionHead->id,
            'director_id' => $director->id,
        ]);
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
        $hr = User::factory()->create(['role' => 'hr']);
        $token = $leader->createToken('group-lead')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'izin',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Keperluan keluarga',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_division');
        $leaveRequest = LeaveRequest::query()->findOrFail($response->json('data.id'));

        $this->actingAs($divisionHead)
            ->patch(route('admin.leave-requests.update', $leaveRequest), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'supervisor_reviewed_by' => $divisionHead->id,
            'reviewed_by' => $divisionHead->id,
        ]);
        $this->assertSame(0, $director->unreadNotifications()->count());
        $this->assertSame(0, $hr->unreadNotifications()->count());

        $this->assertSame(
            ['division'],
            LeaveRequestReview::query()->where('leave_request_id', $leaveRequest->id)
                ->orderBy('id')->pluck('stage')->all(),
        );
    }

    public function test_group_head_request_falls_back_to_hr_when_division_head_is_unavailable(): void
    {
        $department = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $director = User::factory()->create(['role' => 'director']);
        $department->update(['director_id' => $director->id]);
        $leader = User::factory()->create([
            'role' => 'group_lead',
            'department_id' => $department->id,
        ]);
        $hr = User::factory()->create(['role' => 'hr']);
        $token = $leader->createToken('group-lead')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'izin',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Keperluan keluarga',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_hr');
        $leaveRequest = LeaveRequest::query()->findOrFail($response->json('data.id'));

        $this->assertSame(1, $hr->unreadNotifications()->count());
        $this->assertSame(0, $director->unreadNotifications()->count());
        $this->actingAs($hr)
            ->patch(route('admin.leave-requests.update', $leaveRequest), ['status' => 'approved'])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'reviewed_by' => $hr->id,
        ]);
    }

    public function test_employee_without_group_head_or_division_head_is_sent_to_hr(): void
    {
        $department = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $employee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);
        $hr = User::factory()->create(['role' => 'hr']);
        $token = $employee->createToken('employee')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'izin',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Keperluan keluarga',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_hr');
        $leaveRequest = LeaveRequest::query()->findOrFail($response->json('data.id'));

        $this->assertSame(1, $hr->unreadNotifications()->count());
        $this->actingAs($hr)
            ->get(route('admin.leave-requests.index'))
            ->assertOk()
            ->assertSee($employee->email)
            ->assertSee('Tahap Anda: HR')
            ->assertSee('Setujui akhir');
        $this->actingAs($hr)
            ->patch(route('admin.leave-requests.update', $leaveRequest), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'reviewed_by' => $hr->id,
        ]);
    }

    public function test_hr_cannot_approve_its_own_request_but_director_can_complete_it(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $director = User::factory()->create(['role' => 'director']);
        $request = $hr->leaveRequests()->create([
            'type' => 'izin',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Keperluan pribadi',
            'requester_role' => 'hr',
            'status' => 'pending_director',
        ]);

        $this->actingAs($hr)
            ->patch(route('admin.leave-requests.update', $request), ['status' => 'approved'])
            ->assertNotFound();
        $this->actingAs($director)
            ->patch(route('admin.leave-requests.update', $request), ['status' => 'approved'])
            ->assertRedirect(route('admin.leave-requests.index'));

        $this->assertDatabaseHas('leave_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'director_reviewed_by' => $director->id,
            'reviewed_by' => $director->id,
        ]);
    }

    public function test_executive_leave_requests_follow_director_ceo_and_commissioner_stages(): void
    {
        $department = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $director = User::factory()->create([
            'role' => 'director',
            'department_id' => $department->id,
        ]);
        $department->update(['director_id' => $director->id]);
        $ceo = User::factory()->create(['role' => 'ceo']);
        $commissioner = User::factory()->create(['role' => 'commissioner']);

        $directorRequest = $director->leaveRequests()->create([
            'type' => 'izin',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Keperluan pribadi',
            'department_id' => $department->id,
            'requester_role' => 'director',
            'status' => app(LeaveApprovalWorkflow::class)->initialStatus($director),
        ]);
        $this->assertSame('pending_ceo', $directorRequest->status);

        $this->actingAs($director)
            ->patch(route('admin.leave-requests.update', $directorRequest), ['status' => 'approved'])
            ->assertNotFound();
        $this->actingAs($ceo)
            ->patch(route('admin.leave-requests.update', $directorRequest), [
                'status' => 'approved',
                'review_note' => 'Disetujui.',
            ])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $directorRequest->id,
            'status' => 'approved',
            'ceo_status' => 'approved',
            'ceo_reviewed_by' => $ceo->id,
        ]);

        $ceoToken = $ceo->createToken('ceo')->plainTextToken;
        $ceoRequest = $this->withToken($ceoToken)->postJson('/api/leave-requests', [
            'type' => 'izin',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Keperluan pimpinan',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_commissioner')
            ->json('data');
        $this->assertSame(1, $commissioner->notifications()->count());

        $ceoLeaveRequest = LeaveRequest::query()->findOrFail($ceoRequest['id']);
        $this->actingAs($ceo)
            ->patch(route('admin.leave-requests.update', $ceoLeaveRequest), ['status' => 'approved'])
            ->assertNotFound();
        $this->actingAs($commissioner)
            ->patch(route('admin.leave-requests.update', $ceoLeaveRequest), [
                'status' => 'approved',
                'review_note' => 'Disetujui Komisaris.',
            ])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $ceoLeaveRequest->id,
            'status' => 'approved',
            'commissioner_status' => 'approved',
            'commissioner_reviewed_by' => $commissioner->id,
        ]);
        $this->assertDatabaseHas('leave_request_reviews', [
            'leave_request_id' => $ceoLeaveRequest->id,
            'reviewer_id' => $commissioner->id,
            'stage' => 'commissioner',
            'decision' => 'approved',
        ]);
    }

    public function test_department_head_request_is_finalized_by_its_assigned_director(): void
    {
        $department = Department::query()->create([
            'name' => 'Keuangan',
            'is_active' => true,
        ]);
        $divisionHead = User::factory()->create([
            'role' => 'division_head',
            'department_id' => $department->id,
        ]);
        $director = User::factory()->create(['role' => 'director']);
        $otherDirector = User::factory()->create(['role' => 'director']);
        $department->update([
            'supervisor_id' => $divisionHead->id,
            'director_id' => $director->id,
        ]);
        $workflow = app(LeaveApprovalWorkflow::class);
        $request = $divisionHead->leaveRequests()->create([
            'type' => 'izin',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Keperluan pribadi',
            'department_id' => $department->id,
            'requester_role' => 'division_head',
            'status' => $workflow->initialStatus($divisionHead),
        ]);

        $this->assertSame('pending_director', $request->status);
        $this->actingAs($otherDirector)
            ->patch(route('admin.leave-requests.update', $request), ['status' => 'approved'])
            ->assertNotFound();
        $this->actingAs($director)
            ->patch(route('admin.leave-requests.update', $request), ['status' => 'approved'])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'director_reviewed_by' => $director->id,
        ]);
    }

    public function test_executive_request_requires_another_active_commissioner(): void
    {
        $ceo = User::factory()->create(['role' => 'ceo']);
        $token = $ceo->createToken('ceo')->plainTextToken;

        $this->withToken($token)->postJson('/api/leave-requests', [
            'type' => 'izin',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Keperluan pimpinan',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('request');
    }

    public function test_workflow_notifications_reach_only_the_intended_users_and_can_be_read(): void
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
        $outsider = User::factory()->create();
        $hr = User::factory()->create(['role' => 'hr']);
        $employeeToken = $employee->createToken('employee')->plainTextToken;
        $leaderToken = $leader->createToken('leader')->plainTextToken;
        $outsiderToken = $outsider->createToken('outsider')->plainTextToken;

        $request = $this->withToken($employeeToken)
            ->postJson('/api/leave-requests', [
                'type' => 'izin',
                'start_date' => today()->addDay()->toDateString(),
                'end_date' => today()->addDay()->toDateString(),
                'reason' => 'Keperluan keluarga',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_group')
            ->json('data');
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $leader->notifications()->count());
        $this->assertSame(1, $leader->unreadNotifications()->count());

        $this->app['auth']->forgetGuards();
        $leaderInbox = $this->flushHeaders()
            ->withToken($leaderToken)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.title', 'Pengajuan perlu ditinjau');
        $notificationId = $leaderInbox->json('data.0.id');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()
            ->withToken($outsiderToken)
            ->postJson("/api/notifications/{$notificationId}/read")
            ->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()
            ->withToken($leaderToken)
            ->postJson("/api/notifications/{$notificationId}/read")
            ->assertOk();
        $this->app['auth']->forgetGuards();
        $updatedInbox = $this->flushHeaders()
            ->withToken($leaderToken)
            ->getJson('/api/notifications')
            ->assertJsonPath('unread_count', 0);
        $this->assertNotNull($updatedInbox->json('data.0.read_at'));

        $leaveRequest = LeaveRequest::query()->findOrFail($request['id']);
        $this->actingAs($leader)
            ->patch(route('admin.leave-requests.update', $leaveRequest), ['status' => 'approved'])
            ->assertRedirect(route('admin.leave-requests.index'));

        $this->assertSame(1, $employee->unreadNotifications()->count());
        $this->assertSame(0, $divisionHead->unreadNotifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());
        $this->assertSame(0, $hr->unreadNotifications()->count());
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'reviewed_by' => $leader->id,
        ]);
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
        ]);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()
            ->withToken($employeeToken)
            ->postJson('/api/notifications/read-all')
            ->assertOk();
        $this->assertSame(0, $employee->unreadNotifications()->count());
    }

    public function test_only_hr_and_admin_can_open_company_attendance_pages(): void
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

        $this->actingAs($divisionHead)->get(route('admin.attendances.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'director']))
            ->get(route('admin.activity-reports.index'))
            ->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'hr']))
            ->get(route('admin.attendances.index'))
            ->assertOk();
        $this->get(route('admin.payroll.index'))->assertOk();
        $this->get(route('admin.leave-balances.index'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'director']))
            ->get(route('admin.payroll.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_groups_assign_leaders_and_manage_employee_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::query()->create([
            'name' => 'Layanan',
            'is_active' => true,
        ]);
        $leader = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);
        $employee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);

        $this->actingAs($admin)->post(route('admin.work-groups.store'), [
            'department_id' => $department->id,
            'name' => 'Customer Care',
            'leader_id' => $leader->id,
        ])->assertRedirect(route('admin.work-groups.index'));

        $group = WorkGroup::query()->where('name', 'Customer Care')->firstOrFail();
        $this->assertDatabaseHas('users', [
            'id' => $leader->id,
            'role' => 'group_lead',
            'work_group_id' => $group->id,
        ]);
        $this->actingAs($admin)->get(route('admin.work-groups.index'))
            ->assertOk()
            ->assertSee('Customer Care');

        $this->patch(route('admin.employees.group.update', $employee), [
            'work_group_id' => $group->id,
        ])->assertRedirect(route('admin.employees.index'));
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'work_group_id' => $group->id,
        ]);

        $this->patch(route('admin.employees.role.update', $employee), ['role' => 'director'])
            ->assertRedirect(route('admin.employees.index'));
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'role' => 'director',
            'work_group_id' => null,
        ]);
        $this->patch(route('admin.employees.role.update', $leader), ['role' => 'employee'])
            ->assertSessionHasErrors('role');
    }
}
