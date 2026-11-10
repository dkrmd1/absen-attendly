@extends('admin.layouts.app')

@section('title', 'Audit aktivitas')
@section('heading', 'Audit aktivitas')
@section('breadcrumb', 'Audit aktivitas')

@push('styles')
    <style>
        .audit-page{display:grid;gap:20px}
        .audit-card{min-width:0;overflow:hidden;padding:0}
        .audit-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .audit-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700;letter-spacing:-.01em}
        .audit-head p{margin:6px 0 0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .audit-total{flex-shrink:0;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .audit-filters{display:grid;grid-template-columns:minmax(220px,1.5fr) minmax(200px,1fr) repeat(2,minmax(145px,.8fr)) auto auto;align-items:end;gap:12px;padding:18px 24px;border-bottom:1px solid var(--color-bordergray);background:var(--color-white)}
        .audit-field{display:grid;min-width:0;gap:6px}
        .audit-field label{color:var(--color-link);font-size:.8125rem;font-weight:600}
        .audit-field .form-control{width:100%;min-width:0;height:42px}
        .audit-actions{display:flex;gap:8px}
        .audit-actions .btn{height:42px;white-space:nowrap}
        .audit-table-wrap{overflow-x:auto}
        .audit-table{min-width:850px}
        .audit-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.045em;text-transform:uppercase}
        .audit-table td{padding:15px 16px;vertical-align:top}
        .audit-table tbody tr:last-child td{border-bottom:0}
        .audit-actor{min-width:185px}
        .audit-actor strong,.audit-activity strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .audit-actor small,.audit-activity small{margin-top:4px;overflow-wrap:anywhere;line-height:1.5}
        .audit-activity{min-width:280px;max-width:480px}
        .audit-module{white-space:nowrap}
        .audit-date{min-width:145px;color:var(--color-dark);font-variant-numeric:tabular-nums;white-space:nowrap}
        .audit-empty{padding:48px 20px!important;text-align:center!important}
        .audit-empty strong{display:block;color:var(--color-dark);font-size:.9375rem;font-weight:600}
        .audit-empty span{display:block;margin-top:5px;color:var(--color-bodytext);font-size:.8125rem}
        .audit-pagination{display:flex;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--color-bordergray)}
        @media(max-width:1100px){
            .audit-filters{grid-template-columns:repeat(3,minmax(0,1fr))}
            .audit-search{grid-column:span 2}
        }
        @media(max-width:640px){
            .audit-head{flex-direction:column;padding:18px 16px}
            .audit-head h2{font-size:1rem}
            .audit-filters{grid-template-columns:repeat(2,minmax(0,1fr));padding:16px}
            .audit-search{grid-column:1/-1}
            .audit-actions{grid-column:1/-1}
            .audit-actions .btn{flex:1}
            .audit-total{padding:6px 10px;font-size:.75rem}
            .audit-pagination{padding:14px 16px}
        }
    </style>
@endpush

@section('content')
    <div class="audit-page">
        <section class="card audit-card">
            <header class="audit-head">
                <div>
                    <h2>Riwayat aktivitas</h2>
                    <p>Jejak tindakan yang berhasil dilakukan melalui panel admin. Nilai formulir sensitif tidak disimpan.</p>
                </div>
                <span class="audit-total">{{ number_format($logs->total()) }} aktivitas</span>
            </header>

            <form class="audit-filters" method="get" action="{{ route('admin.audit-logs.index') }}">
                <div class="audit-field audit-search">
                    <label for="q">Cari pelaku atau aktivitas</label>
                    <input class="form-control" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}"
                        placeholder="Nama, email, keterangan">
                </div>
                <div class="audit-field">
                    <label for="action">Jenis aktivitas</label>
                    <select class="form-control" id="action" name="action">
                        <option value="">Semua aktivitas</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="audit-field">
                    <label for="from">Dari tanggal</label>
                    <input class="form-control" id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="audit-field">
                    <label for="to">Sampai tanggal</label>
                    <input class="form-control" id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="audit-actions">
                    <button class="btn" type="submit">Cari</button>
                    <a class="btn btn-outline-primary" href="{{ route('admin.audit-logs.index') }}">Reset</a>
                </div>
            </form>

            <div class="audit-table-wrap">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th scope="col">Pelaku</th>
                            <th scope="col">Aktivitas</th>
                            <th scope="col">Modul</th>
                            <th scope="col">Waktu</th>
                            <th scope="col">Alamat IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="audit-actor">
                                    <strong>{{ $log->actor_name }}</strong>
                                    <small>{{ $log->actor_email }}</small>
                                </td>
                                <td class="audit-activity">
                                    <strong>{{ $log->action }}</strong>
                                    <small>{{ $log->description }}</small>
                                </td>
                                <td class="audit-module">{{ $log->module }}</td>
                                <td class="audit-date">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="audit-empty" colspan="5">
                                    <strong>Belum ada aktivitas yang cocok</strong>
                                    <span>Aktivitas berhasil dari panel admin akan tercatat di sini.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="audit-pagination">{{ $logs->links() }}</div>
            @endif
        </section>
    </div>
@endsection
