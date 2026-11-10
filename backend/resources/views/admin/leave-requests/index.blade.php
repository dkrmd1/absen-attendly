@extends('admin.layouts.app')

@section('title', 'Pengajuan izin')
@section('heading', 'Pengajuan izin')
@section('breadcrumb', 'Pengajuan izin')

@push('styles')
    <style>
        .leave-page{display:grid;gap:20px}
        .leave-card{min-width:0;overflow:hidden;padding:0}
        .leave-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .leave-card-actions{display:flex;align-items:center;gap:10px;flex-shrink:0}
        .leave-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700;letter-spacing:-.01em}
        .leave-card-head p{margin:6px 0 0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .leave-count{display:inline-flex;flex-shrink:0;align-items:center;gap:7px;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .leave-count svg{display:block;width:16px;height:16px;flex:0 0 16px}
        .leave-table-wrap{overflow-x:auto}
        .leave-table{min-width:980px}
        .leave-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.045em;text-transform:uppercase}
        .leave-table td{padding:16px;vertical-align:top}
        .leave-table tbody tr:last-child td{border-bottom:0}
        .leave-employee{min-width:185px}
        .leave-employee-main{display:flex;align-items:center;gap:10px}
        .leave-employee .avatar{flex:0 0 auto}
        .leave-employee strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .leave-employee small{margin-top:3px;overflow-wrap:anywhere}
        .leave-employee-meta{margin-top:8px;line-height:1.45}
        .leave-type{min-width:165px}
        .leave-type strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .leave-type small{margin-top:4px;line-height:1.5}
        .leave-reason{min-width:170px;max-width:280px;white-space:normal;overflow-wrap:anywhere;line-height:1.55}
        .leave-reason small{margin-top:6px;line-height:1.5}
        .leave-status{display:grid;min-width:180px;gap:8px}
        .leave-status small,.leave-actions small{line-height:1.5;overflow-wrap:anywhere}
        .leave-actions{display:grid;min-width:190px;gap:9px}
        .leave-actions .form-control{min-width:175px}
        .leave-actions .btn{min-height:34px}
        .leave-action-buttons{display:flex;flex-wrap:wrap;gap:7px}
        .leave-empty{padding:48px 20px!important;text-align:center!important}
        .leave-empty-icon{display:grid;width:44px;height:44px;margin:0 auto 12px;place-items:center;border-radius:50%;background:var(--color-lightprimary);color:var(--color-primary);font-size:22px}
        .leave-empty-icon svg{display:block;width:20px;height:20px;flex:0 0 20px}
        .leave-empty strong{display:block;color:var(--color-dark);font-size:.9375rem;font-weight:600}
        .leave-empty>span:not(.leave-empty-icon){display:block;margin-top:5px;color:var(--color-bodytext);font-size:.8125rem}
        .leave-pagination{display:flex;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--color-bordergray)}
        @media(max-width:640px){
            .leave-card-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .leave-card-head h2{font-size:1rem}
            .leave-count{padding:6px 10px;font-size:.75rem}
            .leave-card-actions{flex-wrap:wrap}
            .leave-pagination{padding:14px 16px}
        }
    </style>
@endpush

@section('content')
    @php
        $statusLabels = [
            'pending' => 'Menunggu Pimpinan Divisi',
            'pending_group' => 'Menunggu Group Head',
            'pending_division' => 'Menunggu Pimpinan Divisi',
            'pending_director' => 'Menunggu direktur',
            'pending_ceo' => 'Menunggu direktur utama',
            'pending_commissioner' => 'Menunggu komisaris',
            'pending_hr' => 'Menunggu HR',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];
        $stageLabels = ['group' => 'Group Head', 'division' => 'Pimpinan Divisi', 'director' => 'Direktur', 'ceo' => 'Direktur utama', 'commissioner' => 'Komisaris', 'hr' => 'HR'];
    @endphp
    <div class="leave-page">
    <section class="card leave-card">
        <div class="leave-card-head">
            <div>
                <h2>Pengajuan dalam cakupan Anda</h2>
                <p>Persetujuan diberikan oleh atasan sesuai jabatan dan struktur organisasi.</p>
            </div>
            <div class="leave-card-actions">
                <span class="leave-count">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="5.5" y="5" width="13" height="16" rx="2"></rect>
                        <path d="M9 5V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V5M9 10h6M9 13.5h6M9 17h3"></path>
                    </svg>
                    {{ $requests->total() }} pengajuan
                </span>
                <a class="btn" href="{{ route('admin.leave-requests.export.xlsx') }}">Unduh XLSX</a>
                <a class="btn btn-outline-primary" href="{{ route('admin.leave-requests.export') }}">CSV</a>
            </div>
        </div>
        <div class="leave-table-wrap">
            <table class="leave-table">
                <thead>
                    <tr>
                        <th scope="col">Karyawan</th>
                        <th scope="col">Jenis & tanggal</th>
                        <th scope="col">Alasan</th>
                        <th scope="col">Status & riwayat</th>
                        <th scope="col">Persetujuan / tindakan</th>
                    </tr>
                </thead>
                <tbody>
            @forelse ($requests as $leaveRequest)
                @php
                    $stage = $reviewStages[$leaveRequest->id] ?? null;
                    $finalReviewer = in_array($stage, ['group', 'hr', 'commissioner'], true)
                        || ($stage === 'division' && in_array($leaveRequest->requester_role, ['employee', 'group_lead'], true))
                        || ($stage === 'director' && in_array($leaveRequest->requester_role, ['hr', 'division_head', 'group_lead'], true))
                        || ($stage === 'ceo' && $leaveRequest->requester_role === 'director');
                    $statusLabel = $statusLabels[$leaveRequest->status] ?? $leaveRequest->status;
                @endphp
                <tr>
                    <td class="leave-employee">
                        <div class="leave-employee-main"><span class="avatar">{{ mb_strtoupper(mb_substr($leaveRequest->user->name, 0, 1)) }}</span><span><strong>{{ $leaveRequest->user->name }}</strong><small>{{ $leaveRequest->user->email }}</small></span></div>
                        <small class="leave-employee-meta">{{ $leaveRequest->department?->name ?? 'Tanpa departemen' }} · {{ $leaveRequest->workGroup?->name ?? 'Tanpa group' }}</small>
                    </td>
                    <td class="leave-type">
                        <strong>{{ ['perjalanan_dinas' => 'Perjalanan Dinas', 'diklat' => 'Diklat'][$leaveRequest->type] ?? ucfirst($leaveRequest->type) }}</strong>
                        <small class="text-xs text-bodytext">{{ $leaveRequest->start_date->translatedFormat('d M Y') }} – {{ $leaveRequest->end_date->translatedFormat('d M Y') }}</small>
                        @if ($leaveRequest->subtype)<small class="text-xs text-bodytext">Kategori: {{ str($leaveRequest->subtype)->replace('_', ' ')->title() }}</small>@endif
                        @if ($leaveRequest->details['destination'] ?? false)<small class="text-xs text-bodytext">Tujuan: {{ $leaveRequest->details['destination'] }}</small>@endif
                        @if ($leaveRequest->details['institution'] ?? false)<small class="text-xs text-bodytext">Penyelenggara: {{ $leaveRequest->details['institution'] }}</small>@endif
                        @if ($leaveRequest->details['start_time'] ?? false)<small class="text-xs text-bodytext">Lembur: {{ $leaveRequest->details['start_time'] }}–{{ $leaveRequest->details['end_time'] ?? '' }}</small>@endif
                    </td>
                    <td class="leave-reason">
                        {{ $leaveRequest->reason }}
                        @if ($leaveRequest->details['reference'] ?? false)<small class="text-xs text-bodytext">Referensi: {{ $leaveRequest->details['reference'] }}</small>@endif
                        @if ($leaveRequest->attachment_path)<a class="text-xs text-bodytext" href="{{ route('admin.leave-requests.attachment', $leaveRequest) }}">Lihat lampiran</a>@endif
                    </td>
                    <td>
                        <div class="leave-status">
                        <span class="badge-md rounded-full text-xs font-medium {{ $leaveRequest->status === 'approved' ? 'bg-lightsuccess text-success' : ($leaveRequest->status === 'rejected' ? 'bg-lighterror text-error' : 'bg-lightwarning text-warning') }}">{{ $statusLabel }}</span>
                        @if ($leaveRequest->group_reviewed_at)<small class="text-xs text-bodytext">Group: {{ $leaveRequest->reviews->firstWhere('stage', 'group')?->reviewer?->name ?? 'Ditinjau' }}</small>@endif
                        @if ($leaveRequest->supervisor_reviewed_at)<small class="text-xs text-bodytext">Divisi: {{ $leaveRequest->supervisorReviewer?->name ?? 'Ditinjau' }}</small>@endif
                        @if ($leaveRequest->director_reviewed_at)<small class="text-xs text-bodytext">Direktur: {{ $leaveRequest->reviews->firstWhere('stage', 'director')?->reviewer?->name ?? 'Ditinjau' }}</small>@endif
                        @if ($leaveRequest->ceo_reviewed_at)<small class="text-xs text-bodytext">Direktur utama: {{ $leaveRequest->ceoReviewer?->name ?? 'Ditinjau' }}</small>@endif
                        @if ($leaveRequest->commissioner_reviewed_at)<small class="text-xs text-bodytext">Komisaris: {{ $leaveRequest->commissionerReviewer?->name ?? 'Ditinjau' }}</small>@endif
                        @if ($leaveRequest->reviewer)<small class="text-xs text-bodytext">Keputusan akhir: {{ $leaveRequest->reviewer->name }}</small>@endif
                        </div>
                    </td>
                    <td>
                        @if ($stage)
                            <form method="post" action="{{ route('admin.leave-requests.update', $leaveRequest) }}" class="leave-actions">
                                @csrf @method('PATCH')
                                <span class="text-xs text-bodytext">Tahap Anda: {{ $stageLabels[$stage] }}</span>
                                @if ($finalReviewer && in_array($leaveRequest->type, ['perjalanan_dinas', 'diklat'], true))
                                    <select class="form-control" name="work_location_id" aria-label="Lokasi penugasan">
                                        <option value="">Pilih lokasi kerja</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                                        @endforeach
                                    </select>
                                    @if ($leaveRequest->type === 'diklat')
                                        <label class="text-xs text-bodytext"><input type="checkbox" name="is_online" value="1"> Diklat online (tanpa GPS)</label>
                                    @endif
                                @endif
                                <input class="form-control" name="review_note" maxlength="1000" placeholder="Catatan (opsional)">
                                <div class="leave-action-buttons">
                                    <button class="btn text-xs py-1 px-2" name="status" value="approved" type="submit">{{ $finalReviewer ? 'Setujui akhir' : 'Setujui & teruskan' }}</button>
                                    <button class="btn text-xs py-1 px-2 bg-lighterror text-error" name="status" value="rejected" type="submit">Tolak</button>
                                </div>
                            </form>
                        @elseif (in_array($leaveRequest->status, ['pending', 'pending_group', 'pending_division', 'pending_director', 'pending_ceo', 'pending_commissioner', 'pending_hr'], true))
                            <span class="text-xs text-bodytext">Menunggu {{ $statusLabels[$leaveRequest->status] ?? 'peninjau' }}.</span>
                        @else
                            <span class="text-xs text-bodytext">{{ $leaveRequest->reviewed_at?->translatedFormat('d M Y') ?? 'Selesai' }}</span>
                        @endif
                        <div class="leave-actions">
                            @foreach ($leaveRequest->reviews as $review)
                                <small class="text-xs text-bodytext">{{ $stageLabels[$review->stage] ?? $review->stage }}: {{ $review->decision === 'approved' ? 'Disetujui' : 'Ditolak' }} · {{ $review->reviewer?->name }}</small>
                                @if ($review->note)<small class="text-xs text-bodytext">{{ $review->note }}</small>@endif
                            @endforeach
                            @if ($leaveRequest->review_note && $leaveRequest->reviews->isEmpty())<small class="text-xs text-bodytext">{{ $leaveRequest->review_note }}</small>@endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="leave-empty">
                        <span class="leave-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <rect x="3.5" y="4.5" width="17" height="16" rx="2"></rect>
                                <path d="M7.5 3v3M16.5 3v3M3.5 9h17M7.5 12.5h3M7.5 16h2"></path>
                            </svg>
                        </span>
                        <strong>Belum ada pengajuan izin</strong>
                        <span>Pengajuan yang masuk dalam cakupan Anda akan muncul di sini.</span>
                    </td>
                </tr>
            @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="leave-pagination">{{ $requests->links() }}</div>
        @endif
    </section>
    </div>
@endsection
