<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_admin_mutations_are_recorded_without_form_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->post(route('admin.locations.store'), [
                'name' => 'Kantor Rahasia',
                'address' => 'Alamat privat',
                'latitude' => '-6.2',
                'longitude' => '106.8',
                'radius_meters' => 150,
                'is_active' => true,
                'password' => 'jangan-simpan-ini',
            ])
            ->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'actor_name' => $admin->name,
            'action' => 'Menambahkan lokasi kerja',
            'module' => 'Lokasi kerja',
            'ip_address' => '203.0.113.8',
        ]);

        $log = AuditLog::query()->sole();
        $this->assertContains('is_active', $log->properties['fields']);
        $this->assertNotContains('password', $log->properties['fields']);
        $this->assertStringNotContainsString('Alamat privat', $log->description);
        $this->assertStringNotContainsString('jangan-simpan-ini', json_encode($log->properties));

        $location = WorkLocation::query()->where('name', 'Kantor Rahasia')->firstOrFail();
        $this->actingAs($admin)
            ->put(route('admin.locations.update', $location), [
                'name' => 'Kantor Pusat',
                'address' => 'Alamat lain',
                'latitude' => '-6.3',
                'longitude' => '106.9',
                'radius_meters' => 180,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.locations.index'));

        $updateLog = AuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame('WorkLocation', $updateLog->subject_type);
        $this->assertSame($location->id, $updateLog->subject_id);
    }

    public function test_failed_mutations_do_not_create_audit_entries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.locations.index'))
            ->post(route('admin.locations.store'), [
                'name' => '',
                'radius_meters' => 10,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.locations.index'))
            ->assertSessionHasErrors(['name', 'radius_meters']);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_can_search_and_filter_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AuditLog::query()->create([
            'actor_user_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'action' => 'Menambahkan lokasi kerja',
            'module' => 'Lokasi kerja',
            'description' => 'Menambahkan lokasi kerja · Lokasi kerja',
            'created_at' => now()->setTime(10, 0),
        ]);
        AuditLog::query()->create([
            'actor_user_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'action' => 'Mengubah jabatan karyawan',
            'module' => 'Karyawan',
            'description' => 'Mengubah jabatan karyawan · Karyawan #10',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', [
                'q' => 'lokasi',
                'action' => 'Menambahkan lokasi kerja',
                'from' => today()->toDateString(),
                'to' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Menambahkan lokasi kerja')
            ->assertDontSee('Karyawan #10');
    }

    public function test_only_admins_can_open_audit_logs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'hr']))
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'employee']))
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }
}
