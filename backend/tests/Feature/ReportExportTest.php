<?php

namespace Tests\Feature;

use App\Models\ActivityReport;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_export_applies_filters_and_neutralizes_spreadsheet_formulas(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operations = Department::query()->create(['name' => 'Operasional', 'is_active' => true]);
        $finance = Department::query()->create(['name' => 'Keuangan', 'is_active' => true]);
        $operationsEmployee = User::factory()->create([
            'name' => '=2+3',
            'department_id' => $operations->id,
        ]);
        $financeEmployee = User::factory()->create(['department_id' => $finance->id]);

        $operationsEmployee->attendances()->create([
            'department_id' => $operations->id,
            'work_date' => today(),
            'check_in_at' => now()->setTime(8, 0),
        ]);
        $additionalEmployees = User::factory()->count(20)->create([
            'department_id' => $operations->id,
        ]);
        foreach ($additionalEmployees as $employee) {
            $employee->attendances()->create([
                'department_id' => $operations->id,
                'work_date' => today(),
                'check_in_at' => now()->setTime(8, 0),
            ]);
        }
        $financeEmployee->attendances()->create([
            'department_id' => $finance->id,
            'work_date' => today(),
            'check_in_at' => now()->setTime(8, 1),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.attendances.export', [
            'date' => today()->toDateString(),
            'department_id' => $operations->id,
        ]));

        $response->assertOk()->assertDownload('absensi-'.today()->toDateString().'.csv');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Lokasi / kegiatan', $csv);
        $this->assertStringContainsString(today()->format('d/m/Y'), $csv);
        $this->assertStringContainsString('Tercatat', $csv);
        $this->assertStringContainsString('Tidak perlu', $csv);
        $this->assertStringContainsString("'=2+3", $csv);
        $this->assertStringContainsString($additionalEmployees->last()->email, $csv);
        $this->assertStringNotContainsString($financeEmployee->email, $csv);
    }

    public function test_leave_export_keeps_group_lead_scope(): void
    {
        $department = Department::query()->create(['name' => 'Operasional', 'is_active' => true]);
        $leader = User::factory()->create([
            'role' => 'group_lead',
            'department_id' => $department->id,
        ]);
        $workGroup = WorkGroup::query()->create([
            'department_id' => $department->id,
            'name' => 'Lapangan',
            'leader_id' => $leader->id,
            'is_active' => true,
        ]);
        $leader->update(['work_group_id' => $workGroup->id]);
        $ownEmployee = User::factory()->create([
            'department_id' => $department->id,
            'work_group_id' => $workGroup->id,
        ]);
        $otherEmployee = User::factory()->create(['department_id' => $department->id]);

        $ownEmployee->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Urusan tim lapangan',
            'department_id' => $department->id,
            'work_group_id' => $workGroup->id,
            'requester_role' => 'employee',
            'status' => 'pending_group',
        ]);
        $otherEmployee->leaveRequests()->create([
            'type' => 'cuti',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'reason' => 'Permohonan di luar grup',
            'department_id' => $department->id,
            'requester_role' => 'employee',
            'status' => 'pending_group',
        ]);

        $response = $this->actingAs($leader)->get(route('admin.leave-requests.export'));

        $response->assertOk()->assertDownload('pengajuan-izin-'.today()->toDateString().'.csv');
        $csv = $response->streamedContent();

        $this->assertStringContainsString($ownEmployee->email, $csv);
        $this->assertStringContainsString('Cuti', $csv);
        $this->assertStringContainsString('Menunggu Group Head', $csv);
        $this->assertStringContainsString(today()->addDay()->format('d/m/Y'), $csv);
        $this->assertStringNotContainsString($otherEmployee->email, $csv);
    }

    public function test_activity_export_includes_report_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $department = Department::query()->create(['name' => 'Operasional', 'is_active' => true]);
        $employee->update(['department_id' => $department->id]);
        ActivityReport::query()->create([
            'user_id' => $employee->id,
            'activity_date' => today(),
            'title' => 'Rekonsiliasi',
            'description' => 'Menyelesaikan laporan harian',
            'hours' => 2.5,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.activity-reports.export'));

        $response->assertOk()->assertDownload('laporan-aktivitas-'.today()->toDateString().'.csv');
        $csv = $response->streamedContent();

        $this->assertStringContainsString($employee->email, $csv);
        $this->assertStringContainsString('Departemen', $csv);
        $this->assertStringContainsString('Operasional', $csv);
        $this->assertStringContainsString('Disetujui', $csv);
        $this->assertStringContainsString(today()->format('d/m/Y'), $csv);
    }

    public function test_attendance_xlsx_has_styled_header_and_searchable_table_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['name' => '=2+3']);
        $employee->attendances()->create([
            'work_date' => today(),
            'check_in_at' => now()->setTime(8, 0),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.attendances.export.xlsx', [
            'date' => today()->toDateString(),
        ]));

        $response->assertOk()->assertDownload('absensi-'.today()->toDateString().'.xlsx');
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $spreadsheet = $this->loadWorkbook($response);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Laporan Absensi', $sheet->getCell('A1')->getValue());
        $this->assertSame('Tanggal kerja', $sheet->getCell('A5')->getValue());
        $this->assertSame('=2+3', $sheet->getCell('B6')->getValue());
        $this->assertSame('A6', $sheet->getFreezePane());
        $this->assertSame('0F6B78', $sheet->getStyle('A5')->getFill()->getStartColor()->getRGB());
        $this->assertSame(
            'A5:L6',
            $sheet->getTableByName('AttendlyReport')?->getAutoFilter()->getRange(),
        );
        $this->assertStringContainsString('Gunakan ikon filter', $sheet->getCell('A4')->getValue());
        $spreadsheet->disconnectWorksheets();
    }

    public function test_leave_and_activity_reports_can_be_downloaded_as_xlsx(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $leaveResponse = $this->actingAs($admin)
            ->get(route('admin.leave-requests.export.xlsx'))
            ->assertOk()
            ->assertDownload('pengajuan-izin-'.today()->toDateString().'.xlsx');
        $leaveWorkbook = $this->loadWorkbook($leaveResponse);
        $leaveSheet = $leaveWorkbook->getActiveSheet();
        $this->assertSame('Laporan Pengajuan Izin dan Cuti', $leaveSheet->getCell('A1')->getValue());
        $this->assertSame('A5:M5', $leaveSheet->getAutoFilter()->getRange());
        $leaveWorkbook->disconnectWorksheets();

        $activityResponse = $this->actingAs($admin)
            ->get(route('admin.activity-reports.export.xlsx'))
            ->assertOk()
            ->assertDownload('laporan-aktivitas-'.today()->toDateString().'.xlsx');
        $activityWorkbook = $this->loadWorkbook($activityResponse);
        $activitySheet = $activityWorkbook->getActiveSheet();
        $this->assertSame('Laporan Aktivitas Karyawan', $activitySheet->getCell('A1')->getValue());
        $this->assertSame('A5:K5', $activitySheet->getAutoFilter()->getRange());
        $activityWorkbook->disconnectWorksheets();
    }

    private function loadWorkbook(TestResponse $response): Spreadsheet
    {
        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);

        $temporaryFile = tempnam(sys_get_temp_dir(), 'attendly-test-');
        $this->assertNotFalse($temporaryFile);
        file_put_contents($temporaryFile, $content);

        try {
            return IOFactory::load($temporaryFile);
        } finally {
            unlink($temporaryFile);
        }
    }
}
