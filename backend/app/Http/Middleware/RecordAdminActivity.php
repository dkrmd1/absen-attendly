<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordAdminActivity
{
    private const ACTIONS = [
        'admin.profile.update' => 'Memperbarui profil',
        'admin.attendance-corrections.update' => 'Meninjau koreksi absensi',
        'admin.leave-requests.update' => 'Memproses persetujuan pengajuan',
        'admin.attendances.review' => 'Meninjau absensi diklat',
        'admin.attendances.selfie-review' => 'Meninjau selfie absensi',
        'admin.activity-reports.update' => 'Meninjau laporan aktivitas',
        'admin.leave-balances.update' => 'Memperbarui saldo cuti',
        'admin.company-holidays.store' => 'Menambahkan hari libur',
        'admin.company-holidays.update' => 'Memperbarui hari libur',
        'admin.company-holidays.destroy' => 'Menghapus hari libur',
        'admin.company-holidays.restore' => 'Memulihkan hari libur',
        'admin.payroll.store' => 'Menambahkan data penggajian',
        'admin.payroll.update' => 'Memperbarui data penggajian',
        'admin.payroll.archive' => 'Mengarsipkan data penggajian',
        'admin.payroll.restore' => 'Memulihkan data penggajian',
        'admin.tax-documents.store' => 'Menambahkan dokumen pajak',
        'admin.tax-documents.update' => 'Memperbarui dokumen pajak',
        'admin.tax-documents.publish' => 'Menerbitkan dokumen pajak',
        'admin.tax-documents.archive' => 'Mengarsipkan dokumen pajak',
        'admin.tax-documents.restore' => 'Memulihkan dokumen pajak',
        'admin.announcements.store' => 'Menambahkan pengumuman',
        'admin.announcements.update' => 'Memperbarui pengumuman',
        'admin.announcements.archive' => 'Mengarsipkan pengumuman',
        'admin.announcements.restore' => 'Memulihkan pengumuman',
        'admin.firebase-messaging.update' => 'Memperbarui pengaturan notifikasi',
        'admin.firebase-messaging.test' => 'Mengirim notifikasi uji',
        'admin.employees.store' => 'Menambahkan karyawan',
        'admin.employees.update' => 'Memperbarui data karyawan',
        'admin.employees.archive' => 'Mengarsipkan akun karyawan',
        'admin.employees.restore' => 'Memulihkan akun karyawan',
        'admin.employees.role.update' => 'Mengubah jabatan karyawan',
        'admin.employees.group.update' => 'Mengubah group kerja karyawan',
        'admin.employees.location.update' => 'Mengubah lokasi utama karyawan',
        'admin.employees.department.update' => 'Mengubah departemen karyawan',
        'admin.employees.shift.update' => 'Mengubah shift karyawan',
        'admin.employees.shift.update' => 'Mengubah shift karyawan',
        'admin.departments.store' => 'Menambahkan departemen',
        'admin.departments.update' => 'Memperbarui departemen',
        'admin.work-groups.store' => 'Menambahkan group kerja',
        'admin.work-groups.update' => 'Memperbarui group kerja',
        'admin.shifts.store' => 'Menambahkan shift kerja',
        'admin.shifts.update' => 'Memperbarui shift kerja',
        'admin.locations.store' => 'Menambahkan lokasi kerja',
        'admin.locations.update' => 'Memperbarui lokasi kerja',
    ];

    private const MODULES = [
        'profile' => 'Profil',
        'attendance-corrections' => 'Koreksi absensi',
        'leave-requests' => 'Pengajuan izin',
        'attendances' => 'Absensi',
        'activity-reports' => 'Aktivitas karyawan',
        'leave-balances' => 'Saldo cuti',
        'company-holidays' => 'Hari libur',
        'payroll' => 'Penggajian',
        'tax-documents' => 'Dokumen pajak',
        'announcements' => 'Pengumuman',
        'firebase-messaging' => 'Notifikasi',
        'employees' => 'Karyawan',
        'departments' => 'Departemen',
        'work-groups' => 'Group kerja',
        'shifts' => 'Shift kerja',
        'locations' => 'Lokasi kerja',
    ];

    private const SAFE_FIELDS = [
        'status',
        'verification_status',
        'selfie_review_status',
        'role',
        'department_id',
        'work_group_id',
        'work_location_id',
        'base_location_id',
        'work_shift_id',
        'is_active',
        'is_online',
        'start_date',
        'end_date',
        'date',
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user() || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $newFlashData = $request->session()->get('_flash.new', []);
        $hasSuccessFlash = array_intersect(['success', 'status'], $newFlashData) !== [];
        $isSuccessfulResponse = $response->isSuccessful() || ($response->isRedirection() && $hasSuccessFlash);

        if (! $isSuccessfulResponse) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName() ?? 'admin.unknown';

        if ($routeName === 'admin.logout' || $routeName === 'admin.audit-logs.index') {
            return $response;
        }

        $action = self::ACTIONS[$routeName] ?? $this->fallbackAction($routeName, $request->method());
        $moduleKey = explode('.', Str::after($routeName, 'admin.'), 2)[0] ?? 'admin';
        $module = self::MODULES[$moduleKey] ?? Str::of($moduleKey)->replace('-', ' ')->title()->toString();
        [$subjectType, $subjectId] = $this->subject($route?->parameters() ?? []);
        $fieldNames = array_values(array_intersect(self::SAFE_FIELDS, array_keys($request->all())));
        $routeParameters = $this->safeRouteParameters($route?->parameters() ?? []);
        $subject = $subjectType
            ? $this->subjectLabel($subjectType).' #'.$subjectId
            : $module;
        $description = $subjectType ? $subject : 'Modul: '.$module;

        if ($fieldNames !== []) {
            $description .= ' · Kolom: '.implode(', ', $fieldNames);
        }

        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'actor_name' => $request->user()->name,
            'actor_email' => $request->user()->email,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => [
                'fields' => $fieldNames,
                'route_parameters' => $routeParameters,
                'route_name' => $routeName,
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{0: ?string, 1: ?int}
     */
    private function subject(array $parameters): array
    {
        foreach ($parameters as $parameter) {
            if ($parameter instanceof Model) {
                return [class_basename($parameter), (int) $parameter->getKey()];
            }
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, int>
     */
    private function safeRouteParameters(array $parameters): array
    {
        $safe = [];

        foreach ($parameters as $key => $value) {
            if ($value instanceof Model) {
                $safe[$key] = (int) $value->getKey();
            } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $safe[$key] = (int) $value;
            }
        }

        return $safe;
    }

    private function subjectLabel(string $subjectType): string
    {
        return match ($subjectType) {
            'User' => 'Karyawan',
            'Attendance' => 'Absensi',
            'AttendanceCorrectionRequest' => 'Koreksi absensi',
            'LeaveRequest' => 'Pengajuan izin',
            'ActivityReport' => 'Laporan aktivitas',
            'Department' => 'Departemen',
            'WorkGroup' => 'Group kerja',
            'WorkShift' => 'Shift kerja',
            'WorkLocation' => 'Lokasi kerja',
            'PayrollRecord' => 'Data penggajian',
            'TaxDocument' => 'Dokumen pajak',
            'CompanyHoliday' => 'Hari libur',
            'Announcement' => 'Pengumuman',
            default => Str::of($subjectType)->snake(' ')->title()->toString(),
        };
    }

    private function fallbackAction(string $routeName, string $method): string
    {
        $operation = match ($method) {
            'POST' => 'Menambahkan',
            'PUT', 'PATCH' => 'Memperbarui',
            'DELETE' => 'Menghapus',
            default => 'Mengubah',
        };
        $routeKey = explode('.', Str::after($routeName, 'admin.'), 2)[0] ?? 'data';
        $module = self::MODULES[$routeKey] ?? Str::of($routeKey)->replace('-', ' ')->title()->toString();

        return $operation.' '.$module;
    }
}
