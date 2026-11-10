<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\WorkLocation;
use App\Services\LeaveApprovalWorkflow;
use App\Services\LeaveRequestNotificationService;
use App\Services\LeaveRequestReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = $request->user()->leaveRequests()
            ->orderByDesc('created_at')
            ->paginate(20);

        $requests->through(fn ($record) => $this->requestData($record));

        return response()->json($requests);
    }

    public function reviewQueue(Request $request, LeaveApprovalWorkflow $workflow): JsonResponse
    {
        $reviewer = $request->user();
        $requests = $workflow->reviewableQuery($reviewer)
            ->with([
                'user:id,name,email,department_id',
                'department:id,name,director_id',
                'workGroup:id,name,leader_id',
            ])
            ->latest()
            ->paginate(30);
        $requests->through(function (LeaveRequest $record) use ($reviewer, $workflow): array {
            $stage = $workflow->stageFor($reviewer, $record);

            return [
                ...$this->requestData($record),
                'applicant_name' => $record->user?->name,
                'applicant_department' => $record->department?->name,
                'work_group' => $record->workGroup?->name,
                'review_stage' => $stage,
                'is_final_reviewer' => $stage !== null && $workflow->isFinalReviewer($stage, $record),
            ];
        });

        return response()->json([
            ...$requests->toArray(),
            'work_locations' => WorkLocation::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function review(
        Request $request,
        LeaveRequest $leaveRequest,
        LeaveApprovalWorkflow $workflow,
        LeaveRequestNotificationService $notifications,
        LeaveRequestReviewService $reviewService,
    ): JsonResponse {
        $record = $reviewService->review($request, $leaveRequest, $workflow, $notifications);

        return response()->json(['data' => $this->requestData($record)]);
    }

    public function store(Request $request, LeaveRequestNotificationService $notifications): JsonResponse
    {
        $attributes = $request->validate([
            'type' => ['required', 'string', 'in:izin,sakit,cuti,lembur,perjalanan_dinas,diklat'],
            'subtype' => ['required_if:type,sakit', 'nullable', 'string', 'in:sakit_tanpa_surat,sakit_dengan_surat,izin_pribadi,izin_keluarga,izin_kedukaan,cuti_tahunan,cuti_menikah,cuti_melahirkan,cuti_keluarga,cuti_khusus'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'details' => ['nullable', 'array'],
            'details.destination' => ['required_if:type,perjalanan_dinas', 'nullable', 'string', 'max:255'],
            'details.institution' => ['required_if:type,diklat', 'nullable', 'string', 'max:255'],
            'details.start_time' => ['required_if:type,lembur', 'nullable', 'date_format:H:i'],
            'details.end_time' => ['required_if:type,lembur', 'nullable', 'date_format:H:i', 'different:details.start_time'],
            'details.work_description' => ['required_if:type,lembur', 'nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $subtype = $attributes['subtype'] ?? match ($attributes['type']) {
            'izin' => 'izin_pribadi',
            'cuti' => 'cuti_tahunan',
            default => null,
        };
        $validSubtypePrefix = match ($attributes['type']) {
            'izin' => 'izin_',
            'sakit' => 'sakit_',
            'cuti' => 'cuti_',
            default => null,
        };
        if ($subtype && $validSubtypePrefix && ! str_starts_with($subtype, $validSubtypePrefix)) {
            throw ValidationException::withMessages([
                'subtype' => 'Kategori tidak sesuai dengan jenis pengajuan.',
            ]);
        }
        if ($attributes['type'] === 'sakit' && $subtype === 'sakit_dengan_surat' && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages([
                'attachment' => 'Surat dokter wajib dilampirkan untuk kategori sakit dengan surat dokter.',
            ]);
        }
        unset($attributes['attachment']);

        $attachmentPath = $request->file('attachment')?->store('employee-request-files', 'local');
        if ($request->hasFile('attachment') && ! $attachmentPath) {
            throw new \RuntimeException('Lampiran pengajuan gagal disimpan ke penyimpanan privat.');
        }

        try {
            $applicant = $request->user();
            $initialStatus = app(LeaveApprovalWorkflow::class)->initialStatus($applicant);
            $leaveRequest = DB::transaction(function () use (
                $applicant,
                $attributes,
                $subtype,
                $attachmentPath,
                $initialStatus,
                $notifications,
            ) {
                $record = $applicant->leaveRequests()->create([
                    ...$attributes,
                    'subtype' => $subtype,
                    'attachment_path' => $attachmentPath,
                    'department_id' => $applicant->department_id,
                    'work_group_id' => $applicant->work_group_id,
                    'requester_role' => $applicant->isDivisionHead()
                        ? 'division_head'
                        : $applicant->role,
                    'status' => $initialStatus,
                    'group_status' => $initialStatus === 'pending_group' ? 'pending' : 'not_required',
                ])->refresh();
                $notifications->notifyReviewers($record);

                return $record;
            });
        } catch (\Throwable $error) {
            if ($attachmentPath) {
                Storage::disk('local')->delete($attachmentPath);
            }

            throw $error;
        }

        return response()->json(['data' => $this->requestData($leaveRequest)], 201);
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

    private function requestData(LeaveRequest $record): array
    {
        return [
            'id' => $record->id,
            'user_id' => $record->user_id,
            'type' => $record->type,
            'subtype' => $record->subtype,
            'start_date' => $record->start_date->toDateString(),
            'end_date' => $record->end_date->toDateString(),
            'reason' => $record->reason,
            'details' => $record->details ?? [],
            'status' => $record->status,
            'group_status' => $record->group_status,
            'supervisor_status' => $record->supervisor_status,
            'director_status' => $record->director_status,
            'ceo_status' => $record->ceo_status,
            'commissioner_status' => $record->commissioner_status,
            'attachment_available' => $record->attachment_path !== null,
            'review_note' => $record->review_note,
            'created_at' => $record->created_at,
        ];
    }
}
