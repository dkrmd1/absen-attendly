@extends('admin.layouts.app')

@section('title', 'Koreksi absensi')
@section('heading', 'Koreksi absensi')
@section('breadcrumb', 'Koreksi absensi')

@push('styles')
    <style>
        .correction-page{display:grid;gap:20px}
        .correction-card{min-width:0;overflow:hidden;padding:0}
        .correction-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .correction-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700;letter-spacing:-.01em}
        .correction-card-head p{margin:6px 0 0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .correction-count{display:inline-flex;flex-shrink:0;align-items:center;gap:7px;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .correction-count svg{display:block;width:16px;height:16px;flex:0 0 16px}
        .correction-table-wrap{overflow-x:auto}
        .correction-table{min-width:1000px}
        .correction-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.045em;text-transform:uppercase}
        .correction-table td{padding:16px;vertical-align:top}
        .correction-table tbody tr:last-child td{border-bottom:0}
        .correction-employee{min-width:155px}
        .correction-employee strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .correction-employee small{margin-top:5px}
        .correction-date{min-width:120px;color:var(--color-dark);font-weight:500;white-space:nowrap}
        .correction-times{min-width:205px}
        .correction-times small{line-height:1.7}
        .correction-reason{min-width:170px;max-width:280px;white-space:normal;overflow-wrap:anywhere;line-height:1.55}
        .correction-audit{display:grid;min-width:185px;gap:8px}
        .correction-audit small{line-height:1.5;overflow-wrap:anywhere}
        .correction-review{display:grid;min-width:190px;gap:9px}
        .correction-review small{line-height:1.45}
        .correction-review .form-control{min-width:175px}
        .correction-actions{display:flex;flex-wrap:wrap;gap:7px}
        .correction-actions .btn{min-height:34px}
        .correction-empty{padding:48px 20px!important;text-align:center!important}
        .correction-empty-icon{display:grid;width:44px;height:44px;margin:0 auto 12px;place-items:center;border-radius:50%;background:var(--color-lightprimary);color:var(--color-primary);font-size:22px}
        .correction-empty-icon svg{display:block;width:20px;height:20px;flex:0 0 20px}
        .correction-empty strong{display:block;color:var(--color-dark);font-size:.9375rem;font-weight:600}
        .correction-empty>span:not(.correction-empty-icon){display:block;margin-top:5px;color:var(--color-bodytext);font-size:.8125rem}
        .correction-pagination{display:flex;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--color-bordergray)}
        @media(max-width:640px){
            .correction-card-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .correction-card-head h2{font-size:1rem}
            .correction-count{padding:6px 10px;font-size:.75rem}
            .correction-pagination{padding:14px 16px}
        }
    </style>
@endpush

@section('content')
    @php
        $statusLabels = [
            'pending_group' => 'Menunggu ketua tim',
            'pending_division' => 'Menunggu kepala divisi',
            'pending_director' => 'Menunggu direktur',
            'pending_ceo' => 'Menunggu direktur utama',
            'pending_commissioner' => 'Menunggu komisaris',
            'pending_hr' => 'Menunggu penerapan HR',
            'applied' => 'Sudah diterapkan',
            'rejected' => 'Ditolak',
        ];
        $stageLabels = [
            'group' => 'Ketua tim',
            'division' => 'Kepala divisi',
            'director' => 'Direktur',
            'ceo' => 'Direktur utama',
            'commissioner' => 'Komisaris',
            'apply' => 'HR',
        ];
    @endphp
    <div class="correction-page">
        <section class="card correction-card">
            <div class="correction-card-head">
                <div>
                    <h2>Permohonan koreksi dan riwayat audit</h2>
                    <p>Perubahan jam hanya diterapkan HR setelah persetujuan atasan. Riwayat audit tersimpan dan tidak dihapus.</p>
                </div>
                <span class="correction-count">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="5.5" y="5" width="13" height="16" rx="2"></rect>
                        <path d="M9 5V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V5M9 10h6M9 13.5h6M9 17h3"></path>
                    </svg>
                    {{ $corrections->total() }} permohonan
                </span>
            </div>
            <div class="correction-table-wrap">
                <table class="correction-table">
                    <thead>
                        <tr>
                            <th scope="col">Karyawan</th>
                            <th scope="col">Tanggal absensi</th>
                            <th scope="col">Jam tercatat → diminta</th>
                            <th scope="col">Alasan</th>
                            <th scope="col">Status & audit</th>
                            <th scope="col">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($corrections as $correction)
                            @php
                                $stage = $reviewStages[$correction->id] ?? null;
                                $statusLabel = $statusLabels[$correction->status] ?? $correction->status;
                            @endphp
                            <tr>
                                <td class="correction-employee">
                                    <strong>{{ $correction->user->name }}</strong>
                                    <small>{{ $correction->department?->name ?? 'Tanpa departemen' }} · {{ $correction->workGroup?->name ?? 'Tanpa tim' }}</small>
                                </td>
                                <td class="correction-date">{{ $correction->attendance?->work_date?->translatedFormat('d M Y') ?? 'Absensi tidak tersedia' }}</td>
                                <td class="correction-times">
                                    <small>Masuk: {{ $correction->original_check_in_at->format('d M Y H:i') }} → {{ $correction->requested_check_in_at->format('d M Y H:i') }}</small>
                                    <small>Pulang: {{ $correction->original_check_out_at?->format('d M Y H:i') ?? '—' }} → {{ $correction->requested_check_out_at?->format('d M Y H:i') ?? '—' }}</small>
                                </td>
                                <td class="correction-reason">{{ $correction->reason }}</td>
                                <td>
                                    <div class="correction-audit">
                                        <span class="badge-md rounded-full text-xs font-medium {{ $correction->status === 'applied' ? 'bg-lightsuccess text-success' : ($correction->status === 'rejected' ? 'bg-lighterror text-error' : 'bg-lightwarning text-warning') }}">{{ $statusLabel }}</span>
                                        @foreach ($correction->reviews as $review)
                                            <small>{{ $stageLabels[$review->stage] ?? $review->stage }}: {{ $review->decision === 'approved' ? 'Disetujui' : ($review->decision === 'applied' ? 'Diterapkan' : 'Ditolak') }} · {{ $review->reviewer?->name }}</small>
                                            @if ($review->note)
                                                <small>{{ $review->note }}</small>
                                            @endif
                                        @endforeach
                                        @if ($correction->applied_at)
                                            <small>Diterapkan {{ $correction->applied_at->translatedFormat('d M Y H:i') }} · {{ $correction->applier?->name ?? 'HR' }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($stage)
                                        <form method="post" action="{{ route('admin.attendance-corrections.update', $correction) }}" class="correction-review">
                                            @csrf
                                            @method('PATCH')
                                            <span class="text-xs text-bodytext">Tahap Anda: {{ $stageLabels[$stage] }}</span>
                                            <input class="form-control" name="note" maxlength="1000" placeholder="{{ $stage === 'apply' ? 'Catatan penerapan (opsional)' : 'Catatan keputusan (wajib saat menolak)' }}">
                                            <div class="correction-actions">
                                                @if ($stage === 'apply')
                                                    <button class="btn text-xs py-1 px-2" name="decision" value="applied" type="submit">Terapkan koreksi</button>
                                                @else
                                                    <button class="btn text-xs py-1 px-2" name="decision" value="approved" type="submit">Setujui & teruskan</button>
                                                @endif
                                                <button class="btn text-xs py-1 px-2 bg-lighterror text-error" name="decision" value="rejected" type="submit">Tolak</button>
                                            </div>
                                        </form>
                                    @elseif (str_starts_with($correction->status, 'pending_'))
                                        <span class="text-xs text-bodytext">{{ $statusLabel }}.</span>
                                    @else
                                        <span class="text-xs text-bodytext">Permohonan selesai.</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="correction-empty">
                                    <span class="correction-empty-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                            <rect x="3.5" y="5" width="17" height="15.5" rx="2"></rect>
                                            <path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17M7 13h4"></path>
                                            <circle cx="15.5" cy="15.5" r="2.5"></circle>
                                            <path d="m17.3 17.3 2 2"></path>
                                        </svg>
                                    </span>
                                    <strong>Belum ada permohonan koreksi</strong>
                                    <span>Permohonan yang masuk dalam cakupan Anda akan muncul di sini.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($corrections->hasPages())
                <div class="correction-pagination">{{ $corrections->links() }}</div>
            @endif
        </section>
    </div>
@endsection
