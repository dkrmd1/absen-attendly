@extends('admin.layouts.app')

@section('title', 'Saldo cuti & hari libur')
@section('heading', 'Saldo cuti & hari libur')
@section('breadcrumb', 'Saldo cuti')
@section('page-description', 'Atur hak cuti tahunan karyawan dan kalender libur perusahaan.')

@push('styles')
    <style>
        .leave-admin-page{display:grid;gap:20px}
        .leave-admin-card{min-width:0;overflow:hidden;padding:0}
        .leave-admin-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .leave-admin-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700}
        .leave-admin-head p{margin:5px 0 0;color:var(--color-bodytext);font-size:.8125rem;line-height:1.55}
        .leave-admin-body{padding:20px 24px}
        .leave-admin-count{display:inline-flex;flex-shrink:0;align-items:center;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .holiday-table-wrap,.leave-balance-table-wrap{overflow-x:auto}
        .holiday-table-wrap table{min-width:600px}
        .leave-balance-table{min-width:800px}
        .leave-admin-card th{padding:13px 16px;background:var(--color-lightgray);font-size:.7rem;letter-spacing:.04em;text-transform:uppercase}
        .leave-admin-card td{padding:14px 16px}
        .leave-admin-card tbody tr:last-child td{border-bottom:0}
        .leave-admin-card tbody tr:hover{background:var(--color-lightprimary)}
        .holiday-create-form{margin-bottom:20px;border:1px solid var(--color-bordergray);border-radius:10px;background:var(--color-lightgray);padding:16px}
        .holiday-form-actions{display:flex;justify-content:flex-end}
        .holiday-form-actions .btn{min-width:170px}
        .holiday-actions{white-space:nowrap}
        .holiday-actions>form{display:inline-flex;align-items:center;vertical-align:middle}
        .balance-panel-head{align-items:flex-end}
        .balance-year-filter{flex-shrink:0}
        .balance-year-filter label{color:var(--color-link);font-size:.8125rem;font-weight:600}
        .balance-year-filter input{width:110px}
        .leave-employee{min-width:210px}
        .leave-employee strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .leave-shift{min-width:170px;color:var(--color-bodytext)}
        .balance-entitlement-form{min-width:235px;flex-wrap:nowrap}
        .balance-entitlement-form .form-control{width:105px}
        .balance-entitlement-form span{color:var(--color-bodytext);font-size:.8125rem}
        .leave-metric{white-space:nowrap;font-variant-numeric:tabular-nums}
        .leave-remaining{color:var(--color-primary);font-weight:700}
        @media(max-width:700px){
            .leave-admin-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .leave-admin-body{padding:16px}
            .leave-admin-count{padding:6px 10px;font-size:.75rem}
            .holiday-create-form{padding:12px}
            .holiday-form-actions .btn{width:100%}
            .balance-panel-head{align-items:stretch}
            .balance-year-filter{width:100%}
            .balance-year-filter input{flex:1}
        }
    </style>
@endpush

@section('content')
    <div class="leave-admin-page">
    <section class="card leave-admin-card holiday-calendar-panel">
        <div class="leave-admin-head">
            <div>
                <h2>Kalender hari libur · {{ $year }}</h2>
                <p>Hari libur yang didaftarkan tidak mengurangi saldo cuti tahunan.</p>
            </div>
            <a class="btn" href="#tambah-hari-libur">＋ Tambah hari libur</a>
        </div>
        <div class="leave-admin-body">
        <form method="post" action="{{ route('admin.company-holidays.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 holiday-create-form" data-popup-create data-dialog-id="tambah-hari-libur" data-dialog-title="Tambah hari libur">
            @csrf
            <div>
                <label class="block mb-1 text-sm font-medium text-link" for="holiday-date">Tanggal libur</label>
                <input class="form-control" id="holiday-date" type="date" name="date" required style="width:100%;display:block">
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium text-link" for="holiday-name">Nama hari libur</label>
                <input class="form-control" id="holiday-name" type="text" name="name" maxlength="255" placeholder="Contoh: Hari libur perusahaan" required style="width:100%;display:block">
            </div>
            <div class="md:col-span-2 holiday-form-actions"><button class="btn" type="submit">Tambah hari libur</button></div>
        </form>
        <div class="overflow-x-auto holiday-table-wrap">
            <table>
                <thead><tr><th>Tanggal</th><th>Nama hari libur</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($holidays as $holiday)
                        <tr>
                            <td>{{ $holiday->date->translatedFormat('d M Y') }}</td>
                            <td>{{ $holiday->name }}</td>
                            <td><span class="badge-md rounded-full text-xs font-medium {{ $holiday->is_active ? 'bg-lightsuccess text-success' : 'bg-lighterror text-error' }}">{{ $holiday->is_active ? 'Aktif' : 'Diarsipkan' }}</span></td>
                            <td class="holiday-actions">
                                @if ($holiday->is_active)
                                <form method="post" action="{{ route('admin.company-holidays.update', $holiday) }}" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-popup-edit data-dialog-title="Ubah hari libur · {{ $holiday->name }}">
                                    @csrf @method('PUT')
                                    <label>Tanggal libur<input class="form-control" type="date" name="date" value="{{ $holiday->date->toDateString() }}" required></label>
                                    <label>Nama hari libur<input class="form-control" name="name" value="{{ $holiday->name }}" maxlength="255" required></label>
                                    <div class="md:col-span-2"><button class="btn" type="submit">Simpan perubahan</button></div>
                                </form>
                                <form method="post" action="{{ route('admin.company-holidays.destroy', $holiday) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn bg-lighterror text-error text-xs py-1 px-2" type="submit">Arsipkan</button>
                                </form>
                                @else
                                    <form method="post" action="{{ route('admin.company-holidays.restore', $holiday) }}" data-confirm-action="restore">
                                        @csrf @method('PATCH')
                                        <button class="btn bg-lightprimary text-primary text-xs py-1 px-2" type="submit">Pulihkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6 text-center text-sm text-bodytext">Belum ada hari libur untuk tahun ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    </section>

    <section class="card leave-admin-card leave-entitlements-panel">
        <div class="leave-admin-head balance-panel-head">
            <div>
                <h2>Hak cuti tahunan karyawan</h2>
                <p>Cuti tahunan baru mengurangi saldo setelah disetujui final oleh admin. Cuti khusus tidak mengurangi saldo ini.</p>
            </div>
            <form method="get" action="{{ route('admin.leave-balances.index') }}" class="flex flex-wrap items-center gap-2 balance-year-filter">
                <label for="year">Tahun</label>
                <input class="form-control" id="year" name="year" type="number" min="2000" max="2100" value="{{ $year }}">
                <button class="btn text-xs py-1 px-2" type="submit">Tampilkan</button>
            </form>
        </div>
        <div class="leave-admin-body">
        <div class="overflow-x-auto leave-balance-table-wrap">
            <table class="leave-balance-table">
                <colgroup>
                    <col class="leave-col-employee">
                    <col class="leave-col-shift">
                    <col class="leave-col-entitlement">
                    <col class="leave-col-used">
                    <col class="leave-col-remaining">
                </colgroup>
                <thead>
                    <tr><th>Karyawan</th><th>Jadwal kerja</th><th>Jatah HRD</th><th>Terpakai</th><th>Sisa</th></tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        @php($summary = $summaries[$employee->id])
                        <tr>
                            <td class="leave-employee"><strong>{{ $employee->name }}</strong><small>{{ $employee->email }}</small></td>
                            <td class="leave-shift">{{ $employee->workShift?->name ?? 'Senin–Jumat (default)' }}</td>
                            <td>
                                <form method="post" action="{{ route('admin.leave-balances.update', $employee) }}" class="flex flex-wrap items-center gap-2 balance-entitlement-form">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="year" value="{{ $year }}">
                                    <input class="form-control" type="number" name="entitlement_days" min="0" max="3650" value="{{ $balances->get($employee->id)?->entitlement_days }}" placeholder="Belum diatur" aria-label="Jatah cuti {{ $employee->name }}" required>
                                    <span>hari</span>
                                    <button class="btn text-xs py-1 px-2" type="submit">Simpan</button>
                                </form>
                            </td>
                            <td class="leave-metric">{{ $summary['configured'] ? $summary['used_days'].' hari' : '—' }}</td>
                            <td class="leave-metric leave-remaining">{{ $summary['configured'] ? $summary['remaining_days'].' hari' : 'Belum diatur' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6 text-center text-sm text-bodytext">Belum ada karyawan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex justify-end mt-4">{{ $employees->links() }}</div>
        </div>
    </section>
    </div>
@endsection
