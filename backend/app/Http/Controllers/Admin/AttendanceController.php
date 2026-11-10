<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Services\CsvDownload;
use App\Services\ExcelReportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
        ]);
        $date = $filters['date'] ?? today()->toDateString();
        $departments = ($request->user()->isAdmin() || $request->user()->isHr())
            ? Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : Department::query()->whereKey($request->user()->department_id)->get(['id', 'name']);
        $attendances = $this->attendanceQuery($request, $filters, $date)
            ->paginate(20)
            ->withQueryString();

        return view('admin.attendances.index', compact('attendances', 'date', 'departments', 'filters'));
    }

    public function export(Request $request, CsvDownload $csv): StreamedResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
        ]);
        $date = $filters['date'] ?? today()->toDateString();

        return $csv->download('absensi-'.$date.'.csv', $this->attendanceExportHeaders(),
            $this->attendanceExportRows($request, $filters, $date));
    }

    public function exportExcel(Request $request, ExcelReportDownload $excel): StreamedResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
        ]);
        $date = $filters['date'] ?? today()->toDateString();
        $department = isset($filters['department_id'])
            ? Department::query()->findOrFail($filters['department_id'])->name
            : 'Semua departemen';

        return $excel->download(
            'absensi-'.$date.'.xlsx',
            'Laporan Absensi',
            'Tanggal: '.Carbon::parse($date)->format('d/m/Y').' | Departemen: '.$department,
            $this->attendanceExportHeaders(),
            $this->attendanceExportRows($request, $filters, $date),
        );
    }

    /**
     * @return list<string>
     */
    private function attendanceExportHeaders(): array
    {
        return [
            'Tanggal kerja',
            'Nama karyawan',
            'Email',
            'Departemen',
            'Lokasi / kegiatan',
            'Check-in',
            'Check-out',
            'Terlambat (menit)',
            'Status absensi',
            'Status selfie',
            'Catatan review',
            'Catatan review selfie',
        ];
    }

    /**
     * @return iterable<list<scalar|null>>
     */
    private function attendanceExportRows(Request $request, array $filters, string $date): iterable
    {
        $verificationLabels = [
            'recorded' => 'Tercatat',
            'pending_review' => 'Menunggu review',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];
        $selfieLabels = [
            'not_required' => 'Tidak perlu',
            'submitted' => 'Terkirim',
            'pending_review' => 'Menunggu review',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'expired' => 'Kedaluwarsa',
        ];
        $rows = $this->attendanceQuery($request, $filters, $date)
            ->lazy(500)
            ->map(function (Attendance $attendance) use ($verificationLabels, $selfieLabels): array {
                return [
                    $attendance->work_date->format('d/m/Y'),
                    $attendance->user->name,
                    $attendance->user->email,
                    $attendance->department?->name,
                    $attendance->attendance_mode === 'online_training'
                        ? 'Diklat online'
                        : ($attendance->workLocation?->name ?? 'Lokasi lama'),
                    $attendance->check_in_at?->format('d/m/Y H:i'),
                    $attendance->check_out_at?->format('d/m/Y H:i'),
                    $attendance->late_minutes,
                    $verificationLabels[$attendance->verification_status]
                        ?? str($attendance->verification_status)->replace('_', ' ')->title()->toString(),
                    $selfieLabels[$attendance->selfie_review_status]
                        ?? ($attendance->selfie_review_status
                            ? str($attendance->selfie_review_status)->replace('_', ' ')->title()->toString()
                            : 'Belum tersedia'),
                    $attendance->review_note,
                    $attendance->selfie_review_note,
                ];
            });

        return $rows;
    }

    private function attendanceQuery(Request $request, array $filters, string $date): Builder
    {
        return Attendance::query()
            ->with([
                'user:id,name,email',
                'department:id,name',
                'workLocation:id,name',
                'reviewer:id,name',
                'selfieReviewer:id,name',
            ])
            ->whereDate('work_date', $date)
            ->when(
                ! $request->user()->isAdmin() && ! $request->user()->isHr(),
                fn ($query) => $query->where('department_id', $request->user()->department_id),
            )
            ->when(
                isset($filters['department_id']),
                fn ($query) => $query->where('department_id', $filters['department_id']),
            )
            ->latest('check_in_at');
    }

    public function review(Request $request, Attendance $attendance): RedirectResponse
    {
        $attributes = $request->validate([
            'verification_status' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $attendance, $attributes) {
            $record = Attendance::query()
                ->whereKey($attendance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $record->attendance_mode !== 'online_training'
                || $record->verification_status !== 'pending_review'
            ) {
                throw ValidationException::withMessages([
                    'verification_status' => 'Absensi ini tidak menunggu peninjauan.',
                ]);
            }

            abort_unless(
                $request->user()->isAdmin()
                || $request->user()->isHr()
                || (
                    $record->department_id === $request->user()->department_id
                    && $record->user_id !== $request->user()->id
                ),
                404,
            );

            $record->update([
                'verification_status' => $attributes['verification_status'],
                'review_note' => $attributes['review_note'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return redirect()->route('admin.attendances.index', [
            'date' => $attendance->work_date->toDateString(),
        ])->with('success', 'Absensi diklat online berhasil ditinjau.');
    }

    public function selfie(Request $request, Attendance $attendance)
    {
        $this->authorizeSelfieAccess($request, $attendance);

        abort_unless(
            $attendance->check_in_selfie_path
            && Storage::disk('local')->exists($attendance->check_in_selfie_path),
            404,
        );

        $response = response()->file(
            Storage::disk('local')->path($attendance->check_in_selfie_path),
        );
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function reviewSelfie(Request $request, Attendance $attendance): RedirectResponse
    {
        $attributes = $request->validate([
            'selfie_review_status' => ['required', 'in:approved,rejected'],
            'selfie_review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $attendance, $attributes) {
            $record = Attendance::query()
                ->whereKey($attendance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeSelfieAccess($request, $record);

            if ($record->selfie_review_status !== 'pending_review') {
                throw ValidationException::withMessages([
                    'selfie_review_status' => 'Absensi ini tidak menunggu review selfie.',
                ]);
            }

            $record->update([
                'selfie_review_status' => $attributes['selfie_review_status'],
                'selfie_review_note' => $attributes['selfie_review_note'] ?? null,
                'selfie_reviewed_by' => $request->user()->id,
                'selfie_reviewed_at' => now(),
            ]);
        });

        return redirect()->route('admin.attendances.index', [
            'date' => $attendance->work_date->toDateString(),
        ])->with('success', 'Review absensi manual berhasil disimpan.');
    }

    private function authorizeSelfieAccess(Request $request, Attendance $attendance): void
    {
        abort_unless(
            $request->user()->isAdmin()
            || $request->user()->isHr()
            || (
                $request->user()->isDepartmentSupervisor()
                && $attendance->department_id === $request->user()->department_id
                && $attendance->user_id !== $request->user()->id
            ),
            404,
        );
    }
}
