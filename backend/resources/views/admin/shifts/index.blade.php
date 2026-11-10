@extends('admin.layouts.app')

@section('title', 'Shift kerja')
@section('heading', 'Shift kerja')
@section('breadcrumb', 'Shift kerja')

@push('styles')
    <style>
        .shift-page{display:grid;gap:20px}
        .shift-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px}
        .shift-toolbar p{margin:0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .shift-card{min-width:0;overflow:hidden;padding:0}
        .shift-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .shift-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700}
        .shift-card-head p{margin:5px 0 0;color:var(--color-bodytext);font-size:.8125rem}
        .shift-count{display:inline-flex;flex-shrink:0;align-items:center;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .shift-table-wrap{overflow-x:auto}
        .shift-table{min-width:800px}
        .shift-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.04em;text-transform:uppercase}
        .shift-table td{padding:16px;vertical-align:middle}
        .shift-table tbody tr:last-child td{border-bottom:0}
        .shift-table tbody tr:hover{background:var(--color-lightprimary)}
        .shift-name{min-width:150px}
        .shift-name strong{color:var(--color-dark);font-size:.875rem;font-weight:600}
        .shift-days-cell{min-width:190px;color:var(--color-bodytext);font-size:.8125rem}
        .shift-members{font-variant-numeric:tabular-nums;font-weight:600}
        .shift-action-cell{min-width:100px}
        .shift-form{display:grid;gap:14px}
        .shift-form>label{display:block}
        .shift-form .form-control{width:100%;min-width:0}
        .shift-time-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .shift-days{display:flex;flex-wrap:wrap;gap:8px}
        .shift-days label{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--color-bordergray);border-radius:8px;background:var(--color-lightgray);padding:8px 10px;color:var(--color-link);font-size:.8125rem}
        .shift-days input{accent-color:var(--color-primary)}
        .shift-edit-active{color:var(--color-bodytext);font-size:.8125rem}
        .shift-form-actions{display:flex;justify-content:flex-end}
        .shift-create-form{max-width:760px}
        .shift-empty{padding:42px 20px!important;text-align:center!important;color:var(--color-bodytext)}
        @media(max-width:640px){
            .shift-toolbar{align-items:stretch;flex-direction:column}
            .shift-toolbar .btn{width:100%}
            .shift-card-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .shift-count{padding:6px 10px;font-size:.75rem}
            .shift-time-grid{grid-template-columns:minmax(0,1fr)}
            .shift-days{gap:6px}
            .shift-days label{padding:7px 8px}
            .shift-form-actions .btn,.shift-create-actions .btn{width:100%}
        }
    </style>
@endpush

@section('content')
    @php($dayNames = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'])
    <div class="shift-page">
        <div class="shift-toolbar">
            <p>Tentukan jam kerja, hari kerja, dan toleransi keterlambatan. Shift diterapkan per karyawan.</p>
            <a class="btn" href="#tambah-shift">＋ Tambah shift</a>
        </div>
        <section class="card shift-card">
            <div class="shift-card-head">
                <div><h2>Daftar shift</h2><p>Jam kerja dan hari aktif yang digunakan perusahaan.</p></div>
                <span class="shift-count">{{ $shifts->count() }} shift</span>
            </div>
            <div class="shift-table-wrap">
            <table class="shift-table">
                <thead><tr><th>Nama shift</th><th>Jam kerja</th><th>Hari kerja</th><th>Toleransi</th><th>Karyawan</th><th>Status</th><th>Tindakan</th></tr></thead>
                <tbody>
                    @forelse ($shifts as $shift)
                        <tr>
                            <td class="shift-name"><strong>{{ $shift->name }}</strong></td>
                            <td>{{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }}</td>
                            <td class="shift-days-cell">{{ collect($shift->work_days)->map(fn ($day) => $dayNames[$day] ?? '')->filter()->join(', ') }}</td>
                            <td>{{ $shift->grace_period_minutes }} menit</td>
                            <td class="shift-members">{{ number_format($shift->employees_count) }}</td>
                            <td><span class="badge-md rounded-full text-xs font-medium {{ $shift->is_active ? 'bg-lightsuccess text-success' : 'bg-lighterror text-error' }}">{{ $shift->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="shift-action-cell">
                                <details>
                                    <summary class="btn bg-lightprimary text-primary text-xs py-1 px-2">Kelola</summary>
                                    <form method="post" action="{{ route('admin.shifts.update', $shift) }}" class="modal-form shift-form" data-dialog-title="Kelola shift · {{ $shift->name }}">
                                        @csrf @method('PUT')
                                        <label class="block mb-1 text-sm font-medium text-link" for="shift-name-{{ $shift->id }}">Nama shift</label>
                                        <input class="form-control" id="shift-name-{{ $shift->id }}" name="name" value="{{ $shift->name }}" maxlength="255" required>
                                        <div class="shift-time-grid">
                                            <div><label class="block mb-1 text-sm font-medium text-link" for="shift-start-{{ $shift->id }}">Mulai</label><input class="form-control" id="shift-start-{{ $shift->id }}" name="start_time" type="time" value="{{ substr($shift->start_time, 0, 5) }}" required></div>
                                            <div><label class="block mb-1 text-sm font-medium text-link" for="shift-end-{{ $shift->id }}">Selesai</label><input class="form-control" id="shift-end-{{ $shift->id }}" name="end_time" type="time" value="{{ substr($shift->end_time, 0, 5) }}" required></div>
                                        </div>
                                        <label class="block mb-1 text-sm font-medium text-link" for="shift-grace-{{ $shift->id }}">Toleransi terlambat (menit)</label>
                                        <input class="form-control" id="shift-grace-{{ $shift->id }}" name="grace_period_minutes" type="number" min="0" max="120" value="{{ $shift->grace_period_minutes }}" required>
                                        <span class="block mb-1 text-sm font-medium text-link">Hari kerja</span>
                                        <div class="shift-days">@foreach ($dayNames as $number => $label)<label><input type="checkbox" name="work_days[]" value="{{ $number }}" @checked(in_array($number, $shift->work_days, true))> {{ $label }}</label>@endforeach</div>
                                        <input type="hidden" name="is_active" value="0">
                                        <label class="shift-edit-active"><input type="checkbox" name="is_active" value="1" @checked($shift->is_active)> Shift aktif</label>
                                        <div class="shift-form-actions"><button class="btn" type="submit">Simpan perubahan</button></div>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="shift-empty">Belum ada shift kerja.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </section>
        <section class="card p-6 modal-form" id="tambah-shift">
        <h3>Tambah shift</h3>
        <form method="post" action="{{ route('admin.shifts.store') }}" class="shift-form shift-create-form">
            @csrf
            <div class="md:col-span-2">
                <label class="block mb-1 text-sm font-medium text-link" for="new-shift-name">Nama shift</label>
                <input class="form-control" id="new-shift-name" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="Contoh: Shift Reguler">
            </div>
            <div class="shift-time-grid">
                <div><label class="block mb-1 text-sm font-medium text-link" for="new-shift-start">Mulai</label><input class="form-control" id="new-shift-start" name="start_time" type="time" value="{{ old('start_time', '08:00') }}" required></div>
                <div><label class="block mb-1 text-sm font-medium text-link" for="new-shift-end">Selesai</label><input class="form-control" id="new-shift-end" name="end_time" type="time" value="{{ old('end_time', '17:00') }}" required></div>
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium text-link" for="new-shift-grace">Toleransi terlambat (menit)</label>
                <input class="form-control" id="new-shift-grace" name="grace_period_minutes" type="number" min="0" max="120" value="{{ old('grace_period_minutes', 0) }}" required>
            </div>
            <div class="md:col-span-2">
                <span class="block mb-1 text-sm font-medium text-link">Hari kerja</span>
                <div class="shift-days">@foreach ($dayNames as $number => $label)<label><input type="checkbox" name="work_days[]" value="{{ $number }}" @checked(in_array($number, old('work_days', [1, 2, 3, 4, 5]), false))> {{ $label }}</label>@endforeach</div>
            </div>
            <input type="hidden" name="is_active" value="1">
            <div class="md:col-span-2 shift-create-actions"><button class="btn" type="submit">Simpan shift</button></div>
        </form>
        </section>
    </div>
@endsection
