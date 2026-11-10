@extends('admin.layouts.app')

@section('title', 'Aktivitas karyawan')
@section('heading', 'Aktivitas karyawan')
@section('breadcrumb', 'Aktivitas')

@push('styles')
    <style>
        .activity-page{display:grid;gap:20px}
        .activity-card{min-width:0;overflow:hidden;padding:0}
        .activity-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .activity-card-actions{display:flex;align-items:center;gap:10px;flex-shrink:0}
        .activity-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700;letter-spacing:-.01em}
        .activity-card-head p{margin:6px 0 0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .activity-count{display:inline-flex;flex-shrink:0;align-items:center;gap:7px;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .activity-count svg{display:block;width:16px;height:16px;flex:0 0 16px}
        .activity-table-wrap{overflow-x:auto}
        .activity-table{min-width:900px}
        .activity-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.045em;text-transform:uppercase}
        .activity-table td{padding:16px;vertical-align:top}
        .activity-table tbody tr:last-child td{border-bottom:0}
        .activity-employee{min-width:170px}
        .activity-employee strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .activity-employee small{margin-top:4px;overflow-wrap:anywhere}
        .activity-date{min-width:120px;color:var(--color-dark);font-weight:500;white-space:nowrap}
        .activity-description{min-width:230px;max-width:380px;white-space:normal;overflow-wrap:anywhere;line-height:1.55}
        .activity-description strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .activity-description small{margin-top:5px;line-height:1.55}
        .activity-duration{min-width:90px;color:var(--color-dark);font-variant-numeric:tabular-nums;font-weight:600;white-space:nowrap}
        .activity-review{display:grid;min-width:185px;gap:8px}
        .activity-review .form-control{min-width:170px}
        .activity-review small{line-height:1.5;overflow-wrap:anywhere}
        .activity-review-actions{display:flex;flex-wrap:wrap;gap:7px}
        .activity-review-actions .btn{min-height:34px}
        .activity-empty{padding:48px 20px!important;text-align:center!important}
        .activity-empty-icon{display:grid;width:44px;height:44px;margin:0 auto 12px;place-items:center;border-radius:50%;background:var(--color-lightprimary);color:var(--color-primary);font-size:22px}
        .activity-empty-icon svg{display:block;width:20px;height:20px;flex:0 0 20px}
        .activity-empty strong{display:block;color:var(--color-dark);font-size:.9375rem;font-weight:600}
        .activity-empty>span:not(.activity-empty-icon){display:block;margin-top:5px;color:var(--color-bodytext);font-size:.8125rem}
        .activity-pagination{display:flex;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--color-bordergray)}
        @media(max-width:640px){
            .activity-card-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .activity-card-head h2{font-size:1rem}
            .activity-count{padding:6px 10px;font-size:.75rem}
            .activity-card-actions{flex-wrap:wrap}
            .activity-pagination{padding:14px 16px}
        }
    </style>
@endpush

@section('content')
    <div class="activity-page">
        <section class="card activity-card">
            <div class="activity-card-head">
                <div>
                    <h2>Laporan aktivitas</h2>
                    <p>Catatan kerja harian yang dikirim karyawan untuk ditinjau.</p>
                </div>
                <div class="activity-card-actions">
                    <span class="activity-count">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <rect x="5.5" y="5" width="13" height="16" rx="2"></rect>
                            <path d="M9 5V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V5M9 10h6M9 13.5h6M9 17h3"></path>
                        </svg>
                        {{ $reports->total() }} laporan
                    </span>
                    <a class="btn" href="{{ route('admin.activity-reports.export.xlsx') }}">Unduh XLSX</a>
                    <a class="btn btn-outline-primary" href="{{ route('admin.activity-reports.export') }}">CSV</a>
                </div>
            </div>

            <div class="activity-table-wrap">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th scope="col">Karyawan</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Aktivitas</th>
                            <th scope="col">Durasi</th>
                            <th scope="col">Status</th>
                            <th scope="col">Tinjauan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr>
                                <td class="activity-employee">
                                    <strong>{{ $report->user->name }}</strong>
                                    <small>{{ $report->user->email }}</small>
                                </td>
                                <td class="activity-date">{{ $report->activity_date->translatedFormat('d M Y') }}</td>
                                <td class="activity-description">
                                    <strong>{{ $report->title }}</strong>
                                    <small>{{ $report->description }}</small>
                                </td>
                                <td class="activity-duration">{{ $report->hours ? $report->hours.' jam' : '—' }}</td>
                                <td>
                                    <span class="badge-md rounded-full text-xs font-medium {{ $report->status === 'approved' ? 'bg-lightsuccess text-success' : ($report->status === 'rejected' ? 'bg-lighterror text-error' : 'bg-lightwarning text-warning') }}">
                                        {{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Perlu revisi'][$report->status] ?? $report->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="activity-review">
                                        @if ($report->status === 'pending' && (! auth()->user()->isDepartmentSupervisor() || $report->user_id !== auth()->id()))
                                            <form method="post" action="{{ route('admin.activity-reports.update', $report) }}" class="activity-review">
                                                @csrf
                                                @method('PATCH')
                                                <input class="form-control" name="review_note" maxlength="1000" placeholder="Catatan tinjauan (opsional)" aria-label="Catatan tinjauan aktivitas">
                                                <div class="activity-review-actions">
                                                    <button class="btn text-xs py-1 px-2" name="status" value="approved" type="submit">Setujui</button>
                                                    <button class="btn text-xs py-1 px-2 bg-lighterror text-error" name="status" value="rejected" type="submit">Minta revisi</button>
                                                </div>
                                            </form>
                                        @elseif ($report->review_note)
                                            <small>{{ $report->review_note }}</small>
                                            @if ($report->reviewer)
                                                <small>Ditinjau oleh {{ $report->reviewer->name }}</small>
                                            @endif
                                        @else
                                            <small>{{ $report->reviewer?->name ?? 'Belum ada catatan tinjauan' }}</small>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="activity-empty">
                                    <span class="activity-empty-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                            <rect x="5.5" y="5" width="13" height="16" rx="2"></rect>
                                            <path d="M9 5V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V5M9 10h6M9 13.5h6M9 17h3"></path>
                                        </svg>
                                    </span>
                                    <strong>Belum ada laporan aktivitas</strong>
                                    <span>Laporan yang dikirim karyawan akan muncul di sini.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($reports->hasPages())
                <div class="activity-pagination">{{ $reports->links() }}</div>
            @endif
        </section>
    </div>
@endsection
