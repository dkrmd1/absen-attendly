<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\WorkLocation;
use App\Services\CsvDownload;
use App\Services\ExcelReportDownload;
use App\Services\LeaveApprovalWorkflow;
use App\Services\LeaveRequestNotificationService;
use App\Services\LeaveRequestReviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    public function index(Request $request, LeaveApprovalWorkflow $workflow): View
    {
        $viewer = $request->user();
        $requests = $this->leaveRequestQuery($request)->paginate(20)->withQueryString();
        $reviewStages = $requests->getCollection()->mapWithKeys(fn (LeaveRequest $record) => [
            $record->id => $workflow->stageFor($viewer, $record),
        ]);
        $locations = (
            $viewer->isHr()
            || $viewer->isGroupLead()
            || $viewer->isDivisionHead()
            || $viewer->isDirector()
            || $viewer->isCeo()
            || $viewer->isCommissioner()
        )
            ? WorkLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.leave-requests.index', compact('requests', 'reviewStages', 'locations'));
    }

    public function export(Request $request, CsvDownload $csv): StreamedResponse
    {
        return $csv->download('pengajuan-izin-'.today()->toDateString().'.csv', $this->leaveExportHeaders(),
            $this->leaveExportRows($request));
    }

    public function exportExcel(Request $request, ExcelReportDownload $excel): StreamedResponse
    {
        return $excel->download(
            'pengajuan-izin-'.today()->toDateString().'.xlsx',
            'Laporan Pengajuan Izin dan Cuti',
            'Daftar pengajuan sesuai cakupan akses akun Anda.',
            $this->leaveExportHeaders(),
            $this->leaveExportRows($request),
        );
    }

    /**
     * @return list<string>
     */
    private function leaveExportHeaders(): array
    {
        return [
            'Nama karyawan',
            'Email',
            'Departemen',
            'Group kerja',
            'Jenis pengajuan',
            'Subjenis',
            'Tanggal mulai',
            'Tanggal selesai',
            'Status',
            'Alasan',
            'Peninjau akhir',
            'Waktu keputusan akhir',
            'Catatan keputusan akhir',
        ];
    }

    /**
     * @return iterable<list<scalar|null>>
     */
    private function leaveExportRows(Request $request): iterable
    {
        $statusLabels = [
            'pending' => 'Menunggu persetujuan',
            'pending_group' => 'Menunggu Group Head',
            'pending_division' => 'Menunggu Pimpinan Divisi',
            'pending_director' => 'Menunggu Direktur',
            'pending_ceo' => 'Menunggu Direktur Utama',
            'pending_commissioner' => 'Menunggu Komisaris',
            'pending_hr' => 'Menunggu HR',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];
        $typeLabels = [
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'cuti' => 'Cuti',
            'lembur' => 'Lembur',
            'perjalanan_dinas' => 'Perjalanan dinas',
            'diklat' => 'Diklat',
        ];
        $rows = $this->leaveRequestQuery($request)
            ->lazy(500)
            ->map(function (LeaveRequest $leaveRequest) use ($statusLabels, $typeLabels): array {
                return [
                    $leaveRequest->user->name,
                    $leaveRequest->user->email,
                    $leaveRequest->department?->name,
                    $leaveRequest->workGroup?->name,
                    $typeLabels[$leaveRequest->type]
                        ?? str($leaveRequest->type)->replace('_', ' ')->title()->toString(),
                    $leaveRequest->subtype
                        ? str($leaveRequest->subtype)->replace('_', ' ')->title()->toString()
                        : null,
                    $leaveRequest->start_date->format('d/m/Y'),
                    $leaveRequest->end_date->format('d/m/Y'),
                    $statusLabels[$leaveRequest->status]
                        ?? str($leaveRequest->status)->replace('_', ' ')->title()->toString(),
                    $leaveRequest->reason,
                    $leaveRequest->reviewer?->name,
                    $leaveRequest->reviewed_at?->format('d/m/Y H:i'),
                    $leaveRequest->review_note,
                ];
            });

        return $rows;
    }

    private function leaveRequestQuery(Request $request): Builder
    {
        $viewer = $request->user();
        $query = LeaveRequest::query()
            ->with([
                'user:id,name,email,department_id,role',
                'department:id,name,is_active,supervisor_id,director_id',
                'workGroup:id,name,is_active,leader_id',
                'reviewer:id,name',
                'supervisorReviewer:id,name',
                'ceoReviewer:id,name',
                'commissionerReviewer:id,name',
                'reviews.reviewer:id,name,role',
            ]);

        if ($viewer->isDirector()) {
            $query->where(fn ($records) => $records
                ->where('status', 'pending_director')
                ->where(fn ($pending) => $pending
                    ->whereNull('department_id')
                    ->orWhereHas('department', fn ($department) => $department->where('director_id', $viewer->id)))
                ->orWhere('user_id', $viewer->id));
        } elseif ($viewer->isCeo()) {
            $query->where(fn ($records) => $records
                ->where('status', 'pending_ceo')
                ->orWhere('user_id', $viewer->id));
        } elseif ($viewer->isCommissioner()) {
            $query->where(fn ($records) => $records
                ->where('status', 'pending_commissioner')
                ->orWhere('user_id', $viewer->id));
        } elseif ($viewer->isGroupLead()) {
            $query->where(fn ($records) => $records
                ->where('work_group_id', $viewer->work_group_id)
                ->orWhere('user_id', $viewer->id));
        } elseif ($viewer->isDivisionHead()) {
            $query->where(fn ($records) => $records
                ->where('department_id', $viewer->department_id)
                ->orWhere('user_id', $viewer->id));
        } elseif (! $viewer->isAdmin() && ! $viewer->isHr()) {
            abort(403);
        }

        return $query->latest();
    }

    public function update(
        Request $request,
        LeaveRequest $leaveRequest,
        LeaveApprovalWorkflow $workflow,
        LeaveRequestNotificationService $notifications,
        LeaveRequestReviewService $reviewService,
    ): RedirectResponse {
        $reviewService->review($request, $leaveRequest, $workflow, $notifications);

        return redirect()->route('admin.leave-requests.index')
            ->with('success', 'Tahap persetujuan berhasil diperbarui.');
    }

    public function attachment(Request $request, int $leaveRequest): StreamedResponse
    {
        $record = LeaveRequest::query()->findOrFail($leaveRequest);
        abort_unless(
            app(LeaveApprovalWorkflow::class)->canSee($request->user(), $record),
            404,
        );
        abort_unless($record->attachment_path, 404);

        return Storage::disk('local')->download($record->attachment_path);
    }
}
