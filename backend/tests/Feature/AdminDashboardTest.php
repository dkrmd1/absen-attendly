<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_guests_and_forbids_employees(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create(['role' => 'employee']))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_access_dashboard_pages_and_employee_cannot_log_in(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('attendly-mark.svg')
            ->assertSee('bg-lightprimary')
            ->assertSee('admin-login-main')
            ->assertSee('max-w-[460px]', false)
            ->assertSee('card-body')
            ->assertSee('name="remember"', false)
            ->assertSee('Masuk dengan akun Attendly');

        $employee = User::factory()->create([
            'email' => 'employee@example.test',
            'password' => 'safe-password',
            'role' => 'employee',
        ]);
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'safe-password',
            'role' => 'admin',
        ]);

        $this->from('/admin/login')->post('/admin/login', [
            'email' => $employee->email,
            'password' => 'safe-password',
        ])->assertSessionHasErrors('email');

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'safe-password',
            'remember' => '1',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(auth()->guard()->check());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('attendly-mark.svg')
            ->assertSee('Grafik Kehadiran 7 Hari Terakhir')
            ->assertSee('Drweb. Hak cipta dilindungi.')
            ->assertSee('.page-wrapper>.body-wrapper{min-height:0;flex:1 1 auto;overflow-y:auto}', false)
            ->assertSee('class="admin-footer"', false)
            ->assertSee('id="main-wrapper"', false)
            ->assertSee('id="application-sidebar-brand"', false)
            ->assertSee('sidebar-account')
            ->assertSee('admin-user-menu')
            ->assertDontSee('sidebar-link dark-sidebar-link w-full" type="submit"', false)
            ->assertSee('class="admin-logout-form"', false)
            ->assertSee('aria-label="Keluar"', false)
            ->assertSee('Profil Saya')
            ->assertSee('Tugas Saya')
            ->assertSee('role="separator"', false)
            ->assertSee('admin-user-menu-logout')
            ->assertDontSee('admin-header-logout')
            ->assertSee('data-profile-menu-toggle', false)
            ->assertSeeInOrder([
                'id="admin-profile-menu"',
                'Profil Saya',
                'Tugas Saya',
                'class="admin-user-menu-divider"',
                'class="admin-logout-form"',
                'admin-user-menu-logout',
            ], false)
            ->assertSee('sidebartoggler')
            ->assertSee('data-hs-overlay="#application-sidebar-brand"', false)
            ->assertSee('themes/tailwindadmin/css/theme.css')
            ->assertSee('id="admin-menu-search"', false)
            ->assertSee('themes/tailwindadmin/js/vendor.min.js')
            ->assertSee('themes/tailwindadmin/css/owl.carousel.min.css')
            ->assertSee('themes/tailwindadmin/js/libs/owl.carousel/owl.carousel.min.js')
            ->assertSee('dashboard-stat-carousel')
            ->assertSee('solar:users-group-rounded-linear')
            ->assertSee('>Group kerja</span>', false)
            ->assertSee('dashboard-panel-grid')
            ->assertSee('dashboard-status-content')
            ->assertSee('dashboard-status-total')
            ->assertSee('@media(min-width:1024px)')
            ->assertSee('owl-carousel owl-theme counter-carousel dashboard-stat-carousel')
            ->assertSee('sidebar-link');
        $this->get('/admin/profile')
            ->assertOk()
            ->assertSee('Pengaturan Keamanan Akun')
            ->assertSee('Simpan perubahan');
        $this->get('/admin/employees')
            ->assertOk()
            ->assertSee($employee->name)
            ->assertSee('employee-table')
            ->assertSee('data-trigger-label="Kelola"', false)
            ->assertSee('Reset kata sandi (opsional)')
            ->assertSee('Nonaktifkan')
            ->assertSee('employee-create-grid')
            ->assertSee('grid grid-cols-1 md:grid-cols-2 gap-4 employee-create-grid', false)
            ->assertSee('form-control');
        $this->get('/admin/attendances')->assertOk();
        $this->get('/admin/leave-requests')->assertOk();
        $this->get('/admin/activity-reports')->assertOk();
        $this->get('/admin/payroll')->assertOk();
        $this->get('/admin/tax-documents')
            ->assertOk()
            ->assertSee('tax-document-form')
            ->assertSee('tax-document-grid')
            ->assertSee('tax-file-input')
            ->assertSee('tax-publish-option');
        $this->get('/admin/announcements')
            ->assertOk()
            ->assertSee('announcement-create-form')
            ->assertSee('announcement-create-grid')
            ->assertSee('announcement-publish-option')
            ->assertSee('announcement-form-actions')
            ->assertSee('grid grid-cols-1 md:grid-cols-2 gap-4 announcement-create-grid', false)
            ->assertSee('form-control');
        $this->get('/admin/locations')
            ->assertOk()
            ->assertSee('Tambah lokasi kerja')
            ->assertSee('location-create-form')
            ->assertSee('id="location-map-create"', false)
            ->assertSee('data-location-map', false)
            ->assertSee('Gunakan lokasi saya')
            ->assertSee('tile.openstreetmap.org')
            ->assertSee('Klik peta untuk memilih titik kantor')
            ->assertSee('location-table')
            ->assertSee('location-col-name')
            ->assertSee('grid grid-cols-1 md:grid-cols-2 gap-4 location-create-form', false)
            ->assertSee('form-control');
        $this->get('/admin/departments')
            ->assertOk()
            ->assertSee('Tambah departemen')
            ->assertSee('department-create-form')
            ->assertSee('grid grid-cols-1 md:grid-cols-2 gap-4 department-form department-create-form', false);
        $this->get('/admin/work-groups')->assertOk()->assertSee('Tambah group');
        $this->get('/admin/settings/office')->assertRedirect('/admin/locations');
    }

    public function test_admin_can_update_profile_and_change_password(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Lama',
            'email' => 'admin@example.test',
            'password' => 'current-safe-password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->patch('/admin/profile', [
                'name' => 'Admin Baru',
                'email' => 'admin.baru@example.test',
                'current_password' => 'current-safe-password',
                'password' => 'new-safe-password-123',
                'password_confirmation' => 'new-safe-password-123',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Admin Baru',
            'email' => 'admin.baru@example.test',
        ]);
        $this->assertTrue(Hash::check('new-safe-password-123', $admin->fresh()->password));
    }

    public function test_profile_password_change_requires_the_current_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from('/admin/profile')
            ->patch('/admin/profile', [
                'name' => $admin->name,
                'email' => $admin->email,
                'current_password' => 'wrong-current-password',
                'password' => 'new-safe-password-123',
                'password_confirmation' => 'new-safe-password-123',
            ])
            ->assertRedirect('/admin/profile')
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_admin_can_edit_archive_and_restore_employee_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $token = $employee->createToken('archive-test')->plainTextToken;

        $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
            'name' => 'Nama Diperbarui',
            'email' => 'updated-employee@example.test',
        ])->assertRedirect(route('admin.employees.index'));
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => 'Nama Diperbarui',
            'email' => 'updated-employee@example.test',
        ]);

        $this->patch(route('admin.employees.archive', $employee))
            ->assertRedirect(route('admin.employees.index'));
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'is_active' => false,
        ]);
        $this->postJson('/api/login', [
            'email' => 'updated-employee@example.test',
            'password' => 'password',
        ])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertForbidden();

        $this->actingAs($admin);
        $this->patch(route('admin.employees.restore', $employee))
            ->assertRedirect(route('admin.employees.index'));
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'is_active' => true,
        ]);
        $this->postJson('/api/login', [
            'email' => 'updated-employee@example.test',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_admin_can_reset_employee_password_and_revoke_existing_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create([
            'role' => 'employee',
            'password' => 'old-employee-password',
        ]);
        $employee->createToken('employee-device');

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), [
                'name' => $employee->name,
                'email' => $employee->email,
                'password' => 'new-employee-password',
                'password_confirmation' => 'new-employee-password',
            ])
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('success', 'Data karyawan berhasil diperbarui dan sesi akun lama telah dikeluarkan.');

        $this->assertTrue(Hash::check('new-employee-password', $employee->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $employee->id,
        ]);
        $this->postJson('/api/login', [
            'email' => $employee->email,
            'password' => 'new-employee-password',
        ])->assertOk();
    }

    public function test_login_handles_legacy_non_bcrypt_password_and_admin_can_reset_it(): void
    {
        $admin = User::factory()->create([
            'email' => 'legacy-admin@example.test',
            'role' => 'admin',
        ]);
        DB::table('users')->where('id', $admin->id)->update([
            'password' => 'legacy-plaintext-password',
        ]);

        $this->from('/admin/login')->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'legacy-plaintext-password',
        ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->artisan('attendly:reset-admin-password', ['email' => $admin->email])
            ->expectsQuestion('Kata sandi baru (minimal 12 karakter)', 'new-safe-admin-password')
            ->expectsQuestion('Ulangi kata sandi baru', 'new-safe-admin-password')
            ->expectsOutputToContain('Kata sandi administrator berhasil diperbarui.')
            ->doesntExpectOutputToContain('new-safe-admin-password')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('new-safe-admin-password', $admin->fresh()->password));
        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'new-safe-admin-password',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_department_supervisor_can_only_review_their_department(): void
    {
        $supervisorDepartment = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $otherDepartment = Department::query()->create([
            'name' => 'Keuangan',
            'is_active' => true,
        ]);
        $supervisor = User::factory()->create([
            'role' => 'employee',
            'department_id' => $supervisorDepartment->id,
            'email' => 'supervisor@example.test',
            'password' => 'safe-password',
        ]);
        $supervisorDepartment->update(['supervisor_id' => $supervisor->id]);
        $ownEmployee = User::factory()->create(['department_id' => $supervisorDepartment->id]);
        $otherEmployee = User::factory()->create(['department_id' => $otherDepartment->id]);
        $ownRequest = $ownEmployee->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Urusan keluarga',
            'department_id' => $supervisorDepartment->id,
            'requester_role' => 'employee',
            'status' => 'pending',
        ]);
        $supervisorRequest = $supervisor->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Permohonan supervisor',
            'department_id' => $supervisorDepartment->id,
            'requester_role' => 'employee',
            'status' => 'pending',
        ]);
        $otherRequest = $otherEmployee->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Urusan pribadi',
            'department_id' => $otherDepartment->id,
            'requester_role' => 'employee',
            'status' => 'pending',
        ]);
        $ownAttendance = $ownEmployee->attendances()->create([
            'department_id' => $supervisorDepartment->id,
            'work_date' => today(),
            'check_in_at' => now(),
            'attendance_mode' => 'online_training',
            'verification_status' => 'pending_review',
        ]);
        $otherAttendance = $otherEmployee->attendances()->create([
            'department_id' => $otherDepartment->id,
            'work_date' => today(),
            'check_in_at' => now(),
            'attendance_mode' => 'online_training',
            'verification_status' => 'pending_review',
        ]);
        $supervisorAttendance = $supervisor->attendances()->create([
            'department_id' => $supervisorDepartment->id,
            'work_date' => today(),
            'check_in_at' => now(),
            'attendance_mode' => 'online_training',
            'verification_status' => 'pending_review',
        ]);

        $this->from('/admin/login')->post('/admin/login', [
            'email' => $supervisor->email,
            'password' => 'safe-password',
        ])->assertRedirect(route('admin.leave-requests.index'));

        $this->get(route('admin.leave-requests.index'))
            ->assertOk()
            ->assertSee($ownEmployee->email)
            ->assertSee('Setujui akhir')
            ->assertDontSee($otherEmployee->email);
        $this->get(route('admin.attendances.index'))->assertForbidden();

        $this->patch(route('admin.leave-requests.update', $ownRequest), [
            'status' => 'approved',
        ])->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $ownRequest->id,
            'status' => 'approved',
            'supervisor_status' => 'approved',
            'supervisor_reviewed_by' => $supervisor->id,
            'reviewed_by' => $supervisor->id,
        ]);
        $this->patch(route('admin.leave-requests.update', $otherRequest), [
            'status' => 'approved',
        ])->assertNotFound();
        $this->patch(route('admin.leave-requests.update', $supervisorRequest), [
            'status' => 'approved',
        ])->assertNotFound();

        $this->patch(route('admin.attendances.review', $ownAttendance), [
            'verification_status' => 'approved',
        ])->assertForbidden();
        $this->patch(route('admin.attendances.review', $otherAttendance), [
            'verification_status' => 'approved',
        ])->assertForbidden();
        $this->patch(route('admin.attendances.review', $supervisorAttendance), [
            'verification_status' => 'approved',
        ])->assertForbidden();

        $this->get('/admin/departments')->assertForbidden();
        $this->get('/admin/employees')->assertForbidden();
        $this->get('/admin/locations')->assertForbidden();
    }

    public function test_admin_can_create_a_location_and_assign_it_as_an_employees_base(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.locations.store'), [
            'name' => 'Kantor Bandung',
            'address' => 'Jl. Asia Afrika',
            'latitude' => '-6.2',
            'longitude' => '106.8',
            'radius_meters' => 150,
            'is_active' => 1,
        ])->assertRedirect(route('admin.locations.index'));

        $location = WorkLocation::query()->where('name', 'Kantor Bandung')->firstOrFail();
        $this->assertDatabaseHas('work_locations', [
            'id' => $location->id,
            'name' => 'Kantor Bandung',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meters' => 150,
        ]);
        $this->actingAs($admin)->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="location-map-'.$location->id.'"', false)
            ->assertSee('value="-6.2"', false)
            ->assertSee('value="106.8"', false);

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'name' => 'Karyawan Bandung',
            'email' => 'bandung@example.test',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'base_location_id' => $location->id,
        ])->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'bandung@example.test',
            'base_location_id' => $location->id,
        ]);
    }

    public function test_admin_can_create_departments_and_assign_them_to_employees(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.departments.store'), [
            'name' => 'Operasional',
            'description' => 'Tim layanan cabang',
            'is_active' => 1,
        ])->assertRedirect(route('admin.departments.index'));

        $department = Department::query()->where('name', 'Operasional')->firstOrFail();
        $employee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);

        $this->get('/admin/employees')
            ->assertOk()
            ->assertSee('Operasional')
            ->assertSee('Departemen');

        $this->actingAs($admin)->put(route('admin.departments.update', $department), [
            'name' => 'Operasional Cabang',
            'description' => 'Tim layanan cabang',
            'is_active' => 1,
        ])->assertRedirect(route('admin.departments.index'));

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'department_id' => $department->id,
        ]);
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Operasional Cabang',
        ]);

        $this->from(route('admin.departments.index'))
            ->put(route('admin.departments.update', $department), [
                'name' => 'Operasional Cabang',
                'description' => 'Tim layanan cabang',
                'is_active' => 0,
            ])->assertSessionHasErrors('is_active');
    }

    public function test_admin_can_create_and_assign_an_employee_shift(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.shifts.store'), [
            'name' => 'Shift Reguler',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'grace_period_minutes' => 10,
            'work_days' => [1, 2, 3, 4, 5],
            'is_active' => 1,
        ])->assertRedirect(route('admin.shifts.index'));

        $shift = WorkShift::query()->where('name', 'Shift Reguler')->firstOrFail();
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($admin)->get(route('admin.shifts.index'))
            ->assertOk()
            ->assertSee('Shift Reguler')
            ->assertSee('shift-create-form')
            ->assertSee('form-control')
            ->assertSee('10 menit');

        $this->post(route('admin.employees.store'), [
            'name' => 'Karyawan Shift',
            'email' => 'shift@example.test',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'work_shift_id' => $shift->id,
        ])->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'shift@example.test',
            'work_shift_id' => $shift->id,
        ]);

        $this->patch(route('admin.employees.shift.update', $employee), [
            'work_shift_id' => $shift->id,
        ])->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'work_shift_id' => $shift->id,
        ]);

        $this->from(route('admin.shifts.index'))
            ->put(route('admin.shifts.update', $shift), [
                'name' => 'Shift Reguler',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'grace_period_minutes' => 10,
                'work_days' => [1, 2, 3, 4, 5],
                'is_active' => 0,
            ])->assertSessionHasErrors('is_active');
    }

    public function test_attendance_report_can_filter_using_department_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operations = Department::query()->create([
            'name' => 'Operasional',
            'is_active' => true,
        ]);
        $finance = Department::query()->create([
            'name' => 'Keuangan',
            'is_active' => true,
        ]);
        $operationsEmployee = User::factory()->create(['department_id' => $operations->id]);
        $financeEmployee = User::factory()->create(['department_id' => $finance->id]);
        $operationsEmployee->attendances()->create([
            'department_id' => $operations->id,
            'work_date' => today(),
            'check_in_at' => now()->setTime(8, 0),
        ]);
        $financeEmployee->attendances()->create([
            'department_id' => $finance->id,
            'work_date' => today(),
            'check_in_at' => now()->setTime(8, 1),
        ]);

        $this->actingAs($admin)->get(route('admin.attendances.index', [
            'department_id' => $operations->id,
        ]))
            ->assertOk()
            ->assertSee($operationsEmployee->email)
            ->assertDontSee($financeEmployee->email);
    }

    public function test_dashboard_attendance_summary_uses_employee_and_approved_leave_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $presentEmployees = User::factory()->count(2)->create(['role' => 'employee']);
        $onLeaveEmployee = User::factory()->create(['role' => 'employee']);
        User::factory()->create(['role' => 'employee']);

        $presentEmployees->first()->attendances()->create([
            'work_date' => today(),
            'check_in_at' => now()->setTime(7, 55),
        ]);
        $presentEmployees->last()->attendances()->create([
            'work_date' => today(),
            'check_in_at' => now()->setTime(8, 5),
        ]);
        $onLeaveEmployee->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'reason' => 'Cuti tahunan',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('data-stat="employees" data-value="4"', false)
            ->assertSee('data-stat="present" data-value="2"', false)
            ->assertSee('data-stat="approved-leave" data-value="1"', false)
            ->assertSee('data-stat="not-checked-in" data-value="1"', false)
            ->assertSee('Persentase kehadiran karyawan dalam tujuh hari terakhir');
    }

    public function test_hr_can_complete_a_pending_leave_request_once(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $this->assertTrue($hr->isHr());
        $this->assertTrue($hr->is_active);
        $employee = User::factory()->create(['role' => 'employee']);
        $leaveRequest = $employee->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => today()->addDays(2),
            'end_date' => today()->addDays(3),
            'reason' => 'Keperluan keluarga',
            'requester_role' => 'employee',
            'status' => 'pending_hr',
        ]);

        $this->actingAs($hr)->patch(route('admin.leave-requests.update', $leaveRequest), [
            'status' => 'approved',
            'review_note' => 'Disetujui.',
        ])->assertRedirect(route('admin.leave-requests.index'));

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'reviewed_by' => $hr->id,
            'review_note' => 'Disetujui.',
        ]);

        $this->actingAs($hr)->patch(route('admin.leave-requests.update', $leaveRequest), [
            'status' => 'rejected',
        ])->assertNotFound();
    }

    public function test_admin_can_manage_private_payroll_tax_documents_and_company_news(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($admin)->post(route('admin.payroll.store'), [
            'user_id' => $employee->id,
            'period_start' => today()->startOfMonth()->toDateString(),
            'gross_amount' => 10000000,
            'deductions_amount' => 1000000,
            'payslip' => UploadedFile::fake()->create('slip.pdf', 12, 'application/pdf'),
        ])->assertRedirect(route('admin.payroll.index'));
        $payroll = $employee->payrollRecords()->firstOrFail();
        $this->assertSame('9000000.00', $payroll->net_amount);
        Storage::disk('local')->assertExists($payroll->payslip_path);
        $this->put(route('admin.payroll.update', $payroll), [
            'user_id' => $employee->id,
            'period_start' => today()->startOfMonth()->toDateString(),
            'gross_amount' => 12000000,
            'deductions_amount' => 1000000,
        ])->assertRedirect(route('admin.payroll.index'));
        $this->assertSame('11000000.00', $payroll->fresh()->net_amount);
        $this->patch(route('admin.payroll.archive', $payroll))
            ->assertRedirect(route('admin.payroll.index'));
        $this->assertDatabaseHas('payroll_records', [
            'id' => $payroll->id,
        ]);
        $this->assertNotNull($payroll->fresh()->archived_at);

        $this->post(route('admin.tax-documents.store'), [
            'user_id' => $employee->id,
            'tax_year' => today()->year,
            'title' => 'Bukti Potong PPh 21',
            'document' => UploadedFile::fake()->create('spt.pdf', 12, 'application/pdf'),
            'publish_now' => 1,
        ])->assertRedirect(route('admin.tax-documents.index'));
        $taxDocument = $employee->taxDocuments()->firstOrFail();
        Storage::disk('local')->assertExists($taxDocument->file_path);
        $this->get(route('admin.tax-documents.file', $taxDocument))->assertOk();
        $this->put(route('admin.tax-documents.update', $taxDocument), [
            'user_id' => $employee->id,
            'tax_year' => today()->year,
            'title' => 'Bukti Potong Revisi',
            'description' => 'Dokumen telah diperbarui.',
            'publish_now' => 1,
        ])->assertRedirect(route('admin.tax-documents.index'));
        $this->assertDatabaseHas('tax_documents', [
            'id' => $taxDocument->id,
            'title' => 'Bukti Potong Revisi',
        ]);
        $this->patch(route('admin.tax-documents.archive', $taxDocument))
            ->assertRedirect(route('admin.tax-documents.index'));
        $this->assertNotNull($taxDocument->fresh()->archived_at);
        $this->get(route('admin.tax-documents.file', $taxDocument))->assertNotFound();

        $this->post(route('admin.announcements.store'), [
            'category' => 'pengumuman',
            'title' => 'Jadwal libur',
            'body' => 'Kantor libur pada tanggal merah.',
            'publish_now' => 1,
        ])->assertRedirect(route('admin.announcements.index'));
        $announcement = Announcement::query()->where('title', 'Jadwal libur')->firstOrFail();
        $this->patch(route('admin.announcements.update', $announcement), [
            'category' => 'pengumuman',
            'title' => 'Jadwal operasional',
            'body' => 'Kantor libur pada tanggal merah.',
            'publish_now' => 1,
        ])->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseHas('announcements', [
            'title' => 'Jadwal operasional',
            'category' => 'pengumuman',
        ]);

        $this->post(route('admin.announcements.store'), [
            'category' => 'berita',
            'title' => 'Draft',
            'body' => 'Belum terbit.',
        ])->assertRedirect(route('admin.announcements.index'));

        $this->post('/admin/logout');
        $this->app['auth']->forgetGuards();
        $this->withToken($employee->createToken('test')->plainTextToken)
            ->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Jadwal operasional');

        $this->actingAs($admin)->patch(route('admin.announcements.archive', $announcement))
            ->assertRedirect(route('admin.announcements.index'));
        $this->assertNotNull($announcement->fresh()->archived_at);
        $this->post('/admin/logout');
        $this->app['auth']->forgetGuards();
        $this->withToken($employee->createToken('test-archived-news')->plainTextToken)
            ->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withToken($employee->createToken('test-archived-resources')->plainTextToken)
            ->getJson('/api/payroll')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/tax-documents')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->app['auth']->forgetGuards();
        $this->actingAs($employee)->get('/admin/payroll')->assertForbidden();
        $this->get('/admin/tax-documents')->assertForbidden();
    }

    public function test_department_head_gives_final_approval_after_hr_configures_leave_balance(): void
    {
        $department = Department::query()->create([
            'name' => 'HR Approval',
            'is_active' => true,
        ]);
        $supervisor = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);
        $department->update(['supervisor_id' => $supervisor->id]);
        $employee = User::factory()->create(['department_id' => $department->id]);
        $request = $employee->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Cuti tahunan',
            'subtype' => 'cuti_tahunan',
            'department_id' => $department->id,
            'requester_role' => 'employee',
            'status' => 'pending',
        ]);
        LeaveBalance::query()->create([
            'user_id' => $employee->id,
            'year' => today()->year,
            'entitlement_days' => 10,
        ]);

        $this->actingAs($supervisor)
            ->patch(route('admin.leave-requests.update', $request), ['status' => 'approved'])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'supervisor_reviewed_by' => $supervisor->id,
            'reviewed_by' => $supervisor->id,
        ]);
    }

    public function test_admin_manages_employee_leave_entitlements_and_company_holidays(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $year = now()->year;

        $this->actingAs($admin)
            ->get(route('admin.leave-balances.index', ['year' => $year]))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertSee('Senin–Jumat (default)')
            ->assertSee('holiday-create-form')
            ->assertSee('balance-year-filter')
            ->assertSee('balance-entitlement-form')
            ->assertSee('style="width:100%;display:block"', false)
            ->assertSee('holiday-form-actions')
            ->assertSee('form-control')
            ->assertSee('class="btn"', false)
            ->assertSee('leave-balance-table')
            ->assertSee('leave-col-entitlement')
            ->assertSee('flex flex-wrap items-center gap-2 balance-entitlement-form', false)
            ->assertSee('leave-employee');
        $this->put(route('admin.leave-balances.update', $employee), [
            'year' => $year,
            'entitlement_days' => 14,
        ])->assertRedirect(route('admin.leave-balances.index', ['year' => $year]));
        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'year' => $year,
            'entitlement_days' => 14,
            'updated_by' => $admin->id,
        ]);

        $holidayDate = Carbon::create($year, 6, 1)->startOfWeek(Carbon::MONDAY)->addDays(2);
        $this->post(route('admin.company-holidays.store'), [
            'date' => $holidayDate->toDateString(),
            'name' => 'Libur internal',
        ])->assertRedirect(route('admin.leave-balances.index', ['year' => $year]));
        $this->assertDatabaseHas('company_holidays', [
            'date' => $holidayDate->toDateString().' 00:00:00',
            'name' => 'Libur internal',
        ]);

        $holiday = CompanyHoliday::query()->whereDate('date', $holidayDate)->firstOrFail();
        $this->put(route('admin.company-holidays.update', $holiday), [
            'date' => $holidayDate->toDateString(),
            'name' => 'Libur internal diperbarui',
        ])->assertRedirect(route('admin.leave-balances.index', ['year' => $year]));
        $this->assertDatabaseHas('company_holidays', [
            'id' => $holiday->id,
            'name' => 'Libur internal diperbarui',
        ]);
        $this->delete(route('admin.company-holidays.destroy', $holiday))
            ->assertRedirect(route('admin.leave-balances.index', ['year' => $year]));
        $this->assertDatabaseHas('company_holidays', [
            'id' => $holiday->id,
            'is_active' => false,
        ]);

        $this->actingAs($employee)
            ->get(route('admin.leave-balances.index'))
            ->assertForbidden();
    }

    public function test_annual_leave_requires_hr_balance_and_deducts_workdays_after_final_approval(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $employee = User::factory()->create(['role' => 'employee']);
        $year = now()->year;
        $startDate = Carbon::create($year, 6, 1)->startOfWeek(Carbon::MONDAY)->addDays(4);
        $endDate = $startDate->copy()->addDays(3);
        $holidayDate = $startDate->toDateString();
        $request = $employee->leaveRequests()->create([
            'type' => 'cuti',
            'subtype' => 'cuti_tahunan',
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'reason' => 'Cuti tahunan',
            'requester_role' => 'employee',
            'status' => 'pending_hr',
        ]);

        $this->actingAs($hr)
            ->from(route('admin.leave-requests.index'))
            ->patch(route('admin.leave-requests.update', $request), ['status' => 'approved'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('leave_requests', [
            'id' => $request->id,
            'status' => 'pending_hr',
        ]);

        LeaveBalance::query()->create([
            'user_id' => $employee->id,
            'year' => $year,
            'entitlement_days' => 1,
        ]);
        CompanyHoliday::query()->create([
            'date' => $holidayDate,
            'name' => 'Libur perusahaan',
        ]);

        $this->patch(route('admin.leave-requests.update', $request), ['status' => 'approved'])
            ->assertRedirect(route('admin.leave-requests.index'));
        $this->assertDatabaseHas('leave_requests', [
            'id' => $request->id,
            'status' => 'approved',
        ]);
        $this->app['auth']->forgetGuards();
        $this->withToken($employee->createToken('test')->plainTextToken)
            ->getJson('/api/leave-balance')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.used_days', 1)
            ->assertJsonPath('data.remaining_days', 0);

        $excessDate = $endDate->copy()->addDay();
        $excessRequest = $employee->leaveRequests()->create([
            'type' => 'cuti',
            'subtype' => 'cuti_tahunan',
            'start_date' => $excessDate->toDateString(),
            'end_date' => $excessDate->toDateString(),
            'reason' => 'Cuti tambahan',
            'requester_role' => 'employee',
            'status' => 'pending_hr',
        ]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($hr)
            ->from(route('admin.leave-requests.index'))
            ->patch(route('admin.leave-requests.update', $excessRequest), ['status' => 'approved'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('leave_requests', [
            'id' => $excessRequest->id,
            'status' => 'pending_hr',
        ]);
    }

    public function test_company_attendance_selfies_are_restricted_to_hr_and_admin(): void
    {
        Storage::fake('local');
        $department = Department::query()->create([
            'name' => 'Operasional Selfie',
            'is_active' => true,
        ]);
        $otherDepartment = Department::query()->create([
            'name' => 'Keuangan Selfie',
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
        $otherEmployee = User::factory()->create([
            'role' => 'employee',
            'department_id' => $otherDepartment->id,
        ]);
        $selfiePath = 'attendance-selfies/private-test.jpg';
        Storage::disk('local')->put($selfiePath, 'private selfie bytes');
        $attendance = $employee->attendances()->create([
            'department_id' => $department->id,
            'work_date' => today(),
            'check_in_at' => now(),
            'check_in_selfie_path' => $selfiePath,
            'selfie_review_status' => 'submitted',
        ]);
        $ownAttendance = $supervisor->attendances()->create([
            'department_id' => $department->id,
            'work_date' => today(),
            'check_in_at' => now(),
            'check_in_selfie_path' => $selfiePath,
            'selfie_review_status' => 'submitted',
        ]);
        $otherAttendance = $otherEmployee->attendances()->create([
            'department_id' => $otherDepartment->id,
            'work_date' => today(),
            'check_in_at' => now(),
            'check_in_selfie_path' => $selfiePath,
            'selfie_review_status' => 'submitted',
        ]);
        $manualAttendance = $employee->attendances()->create([
            'department_id' => $department->id,
            'work_date' => today()->subDay(),
            'check_in_at' => now()->subDay(),
            'selfie_review_status' => 'pending_review',
        ]);

        $this->actingAs($supervisor)
            ->get(route('admin.attendances.selfie', $attendance))
            ->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $selfieResponse = $this->actingAs($admin)
            ->get(route('admin.attendances.selfie', $attendance))
            ->assertOk();
        $this->assertStringContainsString('private', $selfieResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $selfieResponse->headers->get('Cache-Control'));

        $this->get(route('admin.attendances.selfie', $ownAttendance))->assertOk();
        $this->get(route('admin.attendances.selfie', $otherAttendance))->assertOk();

        $this->patch(route('admin.attendances.selfie-review', $manualAttendance), [
            'selfie_review_status' => 'approved',
            'selfie_review_note' => 'Diverifikasi secara manual.',
        ])->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'id' => $manualAttendance->id,
            'selfie_review_status' => 'approved',
            'selfie_reviewed_by' => $admin->id,
            'selfie_review_note' => 'Diverifikasi secara manual.',
        ]);

        $this->patch(route('admin.attendances.selfie-review', $ownAttendance), [
            'selfie_review_status' => 'approved',
        ])->assertSessionHasErrors('selfie_review_status');
    }

    public function test_approving_business_travel_creates_assignment_and_rejects_overlaps(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $employee = User::factory()->create(['role' => 'employee']);
        $location = WorkLocation::query()->create([
            'name' => 'Cabang Bandung',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meters' => 100,
        ]);
        $travel = $employee->leaveRequests()->create([
            'type' => 'perjalanan_dinas',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(3),
            'reason' => 'Kunjungan cabang',
            'requester_role' => 'employee',
            'status' => 'pending_hr',
        ]);

        $this->actingAs($hr)->patch(route('admin.leave-requests.update', $travel), [
            'status' => 'approved',
            'work_location_id' => $location->id,
        ])->assertRedirect(route('admin.leave-requests.index'));

        $this->assertDatabaseHas('work_assignments', [
            'user_id' => $employee->id,
            'work_location_id' => $location->id,
            'leave_request_id' => $travel->id,
            'is_online' => false,
        ]);

        $overlap = $employee->leaveRequests()->create([
            'type' => 'perjalanan_dinas',
            'start_date' => today()->addDays(2),
            'end_date' => today()->addDays(4),
            'reason' => 'Penugasan lain',
            'requester_role' => 'employee',
            'status' => 'pending_hr',
        ]);

        $this->actingAs($hr)->from(route('admin.leave-requests.index'))
            ->patch(route('admin.leave-requests.update', $overlap), [
                'status' => 'approved',
                'work_location_id' => $location->id,
            ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $overlap->id,
            'status' => 'pending_hr',
        ]);
    }

    public function test_admin_can_approve_online_training_attendance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $attendance = $employee->attendances()->create([
            'work_date' => today(),
            'check_in_at' => now(),
            'attendance_mode' => 'online_training',
            'verification_status' => 'pending_review',
        ]);

        $this->actingAs($admin)->patch(route('admin.attendances.review', $attendance), [
            'verification_status' => 'approved',
            'review_note' => 'Kehadiran terverifikasi.',
        ])->assertRedirect(route('admin.attendances.index', ['date' => today()->toDateString()]));

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'verification_status' => 'approved',
            'reviewed_by' => $admin->id,
            'review_note' => 'Kehadiran terverifikasi.',
        ]);
    }

    public function test_approving_online_training_creates_an_assignment_without_a_location(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $employee = User::factory()->create(['role' => 'employee']);
        $training = $employee->leaveRequests()->create([
            'type' => 'diklat',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(2),
            'reason' => 'Pelatihan daring',
            'requester_role' => 'employee',
            'status' => 'pending_hr',
        ]);

        $this->actingAs($hr)->patch(route('admin.leave-requests.update', $training), [
            'status' => 'approved',
            'is_online' => 1,
        ])->assertRedirect(route('admin.leave-requests.index'));

        $this->assertDatabaseHas('work_assignments', [
            'user_id' => $employee->id,
            'leave_request_id' => $training->id,
            'work_location_id' => null,
            'is_online' => true,
        ]);
    }

    public function test_rejected_online_attendance_is_not_counted_as_present(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $employee->attendances()->create([
            'work_date' => today(),
            'check_in_at' => now(),
            'attendance_mode' => 'online_training',
            'verification_status' => 'rejected',
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('data-stat="present" data-value="0"', false)
            ->assertSee('data-stat="not-checked-in" data-value="1"', false);
    }

    public function test_create_admin_command_creates_an_admin_with_a_hashed_password(): void
    {
        $this->artisan('attendly:create-admin')
            ->expectsQuestion('Nama administrator', 'Admin Attendly')
            ->expectsQuestion('Email administrator', 'ADMIN@example.test')
            ->expectsQuestion('Kata sandi (minimal 12 karakter)', 'safe-admin-password')
            ->expectsQuestion('Ulangi kata sandi', 'safe-admin-password')
            ->expectsOutputToContain('Administrator berhasil dibuat.')
            ->doesntExpectOutputToContain('safe-admin-password')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('safe-admin-password', $admin->password));
    }
}
