<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityReport;
use App\Services\CsvDownload;
use App\Services\ExcelReportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityReportController extends Controller
{
    public function index(Request $request): View
    {
        $reports = $this->activityQuery($request)
            ->paginate(25);

        return view('admin.activity-reports.index', compact('reports'));
    }

    public function export(Request $request, CsvDownload $csv): StreamedResponse
    {
        return $csv->download('laporan-aktivitas-'.today()->toDateString().'.csv', $this->activityExportHeaders(),
            $this->activityExportRows($request));
    }

    public function exportExcel(Request $request, ExcelReportDownload $excel): StreamedResponse
    {
        return $excel->download(
            'laporan-aktivitas-'.today()->toDateString().'.xlsx',
            'Laporan Aktivitas Karyawan',
            'Catatan kerja harian sesuai cakupan akses akun Anda.',
            $this->activityExportHeaders(),
            $this->activityExportRows($request),
        );
    }

    /**
     * @return list<string>
     */
    private function activityExportHeaders(): array
    {
        return [
            'Tanggal',
            'Nama karyawan',
            'Email',
            'Departemen',
            'Judul aktivitas',
            'Deskripsi',
            'Durasi (jam)',
            'Status',
            'Ditinjau oleh',
            'Waktu tinjauan',
            'Catatan tinjauan',
        ];
    }

    /**
     * @return iterable<list<scalar|null>>
     */
    private function activityExportRows(Request $request): iterable
    {
        $statusLabels = [
            'pending' => 'Menunggu review',
            'approved' => 'Disetujui',
            'rejected' => 'Perlu revisi',
        ];
        $rows = $this->activityQuery($request)
            ->lazy(500)
            ->map(fn (ActivityReport $report): array => [
                $report->activity_date->format('d/m/Y'),
                $report->user->name,
                $report->user->email,
                $report->user->department?->name,
                $report->title,
                $report->description,
                $report->hours,
                $statusLabels[$report->status]
                    ?? str($report->status)->replace('_', ' ')->title()->toString(),
                $report->reviewer?->name,
                $report->reviewed_at?->format('d/m/Y H:i'),
                $report->review_note,
            ]);

        return $rows;
    }

    private function activityQuery(Request $request): Builder
    {
        return ActivityReport::query()
            ->with(['user:id,name,email,department_id', 'user.department:id,name', 'reviewer:id,name'])
            ->when(
                ! $request->user()->isAdmin() && ! $request->user()->isHr(),
                fn ($query) => $query->whereHas(
                    'user',
                    fn ($users) => $users->where('department_id', $request->user()->department_id),
                ),
            )
            ->latest('activity_date');
    }

    public function update(Request $request, ActivityReport $activityReport): RedirectResponse
    {
        abort_unless(
            $request->user()->isAdmin()
            || $request->user()->isHr()
            || (
                $activityReport->user->department_id === $request->user()->department_id
                && $activityReport->user_id !== $request->user()->id
            ),
            404,
        );
        $attributes = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($activityReport->status === 'pending', 409);

        $activityReport->update([
            ...$attributes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.activity-reports.index')
            ->with('success', 'Aktivitas berhasil ditinjau.');
    }
}
