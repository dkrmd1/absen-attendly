@extends('admin.layouts.app')

@section('title', 'Info gaji')
@section('heading', 'Info gaji')
@section('breadcrumb', 'Info gaji')

@section('content')
    <div class="admin-resource-page">
    <div class="admin-resource-toolbar"><p>Kelola dokumen gaji karyawan dengan akses privat.</p><a class="btn" href="#tambah-slip-gaji">＋ Terbitkan slip gaji</a></div>
    <section class="card admin-resource-card modal-form" id="tambah-slip-gaji">
        <div class="admin-resource-card-head panel-head"><div><h2>Terbitkan slip gaji</h2><p>Slip dan nominal hanya terlihat oleh karyawan pemilik serta admin.</p></div></div>
        <div class="admin-resource-card-body">
        <form method="post" action="{{ route('admin.payroll.store') }}" enctype="multipart/form-data" class="admin-resource-form">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label>Karyawan<select class="form-control" name="user_id" required><option value="">Pilih karyawan</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->email }}</option>@endforeach</select></label>
                <label>Periode (gunakan tanggal 1)<input class="form-control" type="date" name="period_start" required></label>
                <label>Gaji bruto (Rp)<input class="form-control" type="number" min="0" step="1000" name="gross_amount" required></label>
                <label>Total potongan (Rp)<input class="form-control" type="number" min="0" step="1000" name="deductions_amount" value="0" required></label>
                <label class="md:col-span-2">Slip gaji PDF (opsional)<input class="form-control" type="file" name="payslip" accept="application/pdf"></label>
            </div>
            <div class="admin-resource-form-actions"><button class="btn" type="submit">Simpan data gaji</button></div>
        </form>
        </div>
    </section>
    <section class="card admin-resource-card">
        <div class="admin-resource-card-head"><div><h2>Riwayat payroll</h2><p>Nominal hanya ditampilkan pada halaman admin; supervisor tidak memiliki akses.</p></div><span class="admin-resource-count">{{ $records->total() }} data</span></div>
        <div class="admin-resource-table-wrap"><table class="admin-resource-table"><thead><tr><th>Karyawan</th><th>Periode</th><th>Bruto</th><th>Potongan</th><th>Netto</th><th>Slip</th><th>Tindakan</th></tr></thead><tbody>
            @forelse ($records as $record)
                <tr>
                    <td><strong>{{ $record->user->name }}</strong><small>{{ $record->user->email }}</small></td>
                    <td class="whitespace-nowrap">{{ $record->period_start->translatedFormat('F Y') }}</td>
                    <td class="whitespace-nowrap">Rp {{ number_format((float) $record->gross_amount, 0, ',', '.') }}</td>
                    <td class="whitespace-nowrap">Rp {{ number_format((float) $record->deductions_amount, 0, ',', '.') }}</td>
                    <td class="whitespace-nowrap"><strong>Rp {{ number_format((float) $record->net_amount, 0, ',', '.') }}</strong></td>
                    <td>@if ($record->payslip_path && ! $record->archived_at)<a class="text-primary hover:text-primaryemphasis" href="{{ route('admin.payroll.payslip', $record) }}">Unduh PDF</a>@else — @endif</td>
                    <td class="admin-resource-actions">
                        @if ($record->archived_at)
                            <span class="badge-md rounded-full text-xs font-medium bg-lighterror text-error">Diarsipkan</span>
                            <form method="post" action="{{ route('admin.payroll.restore', $record) }}" data-confirm-action="restore">
                                @csrf @method('PATCH')
                                <button class="btn bg-lightprimary text-primary text-xs py-1 px-2" type="submit">Pulihkan</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.payroll.update', $record) }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-popup-edit data-dialog-title="Ubah data gaji · {{ $record->user->name }}">
                                @csrf @method('PUT')
                                <label>Karyawan
                                    <select class="form-control" name="user_id" required>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" @selected($record->user_id === $employee->id)>{{ $employee->name }} · {{ $employee->email }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Periode
                                    <input class="form-control" type="date" name="period_start" value="{{ $record->period_start->toDateString() }}" required>
                                </label>
                                <label>Gaji bruto (Rp)<input class="form-control" type="number" min="0" step="1000" name="gross_amount" value="{{ $record->gross_amount }}" required></label>
                                <label>Total potongan (Rp)<input class="form-control" type="number" min="0" step="1000" name="deductions_amount" value="{{ $record->deductions_amount }}" required></label>
                                <label class="md:col-span-2">Ganti slip PDF (opsional)<input class="form-control" type="file" name="payslip" accept="application/pdf"></label>
                                <div class="md:col-span-2"><button class="btn" type="submit">Simpan perubahan</button></div>
                            </form>
                            <form method="post" action="{{ route('admin.payroll.archive', $record) }}" data-confirm-action="archive">
                                @csrf @method('PATCH')
                                <button class="btn bg-lighterror text-error text-xs py-1 px-2" type="submit">Arsipkan</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="admin-resource-empty">Belum ada data gaji.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="admin-resource-pagination flex justify-end">{{ $records->links() }}</div>
    </section>
    </div>
@endsection
