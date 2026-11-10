@extends('admin.layouts.app')

@section('title', 'Dokumen SPT')
@section('heading', 'Dokumen SPT')
@section('breadcrumb', 'SPT')

@section('content')
    <div class="admin-resource-page">
    <div class="admin-resource-toolbar"><p>Simpan dokumen pajak secara privat untuk pemiliknya.</p><a class="btn" href="#unggah-spt">＋ Unggah dokumen</a></div>
    <section class="card admin-resource-card modal-form" id="unggah-spt">
        <div class="admin-resource-card-head panel-head"><div><h2>Unggah dokumen pajak</h2><p>Dokumen PDF tersimpan privat dan hanya dapat diunduh pemilik atau admin.</p></div></div>
        <div class="admin-resource-card-body">
        <form method="post" action="{{ route('admin.tax-documents.store') }}" enctype="multipart/form-data" class="tax-document-form admin-resource-form">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 tax-document-grid">
                <div>
                    <label class="block mb-1 text-sm font-medium text-link" for="tax-user">Karyawan</label>
                    <select class="form-control" id="tax-user" name="user_id" required><option value="">Pilih karyawan</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->email }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-link" for="tax-year">Tahun pajak</label>
                    <input class="form-control" id="tax-year" type="number" min="2000" max="2100" name="tax_year" value="{{ now()->year }}" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1 text-sm font-medium text-link" for="tax-title">Nama dokumen</label>
                    <input class="form-control" id="tax-title" name="title" maxlength="160" placeholder="Contoh: Bukti Potong PPh 21" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1 text-sm font-medium text-link" for="tax-description">Keterangan</label>
                    <textarea class="form-control" id="tax-description" name="description" rows="3"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1 text-sm font-medium text-link" for="tax-document">File PDF</label>
                    <input class="form-control tax-file-input" id="tax-document" type="file" name="document" accept="application/pdf" required>
                    <small class="tax-file-hint">Format PDF. Dokumen disimpan secara privat.</small>
                </div>
                <div class="md:col-span-2 tax-publish-option">
                    <label><input type="checkbox" name="publish_now" value="1"> Langsung tampilkan di akun karyawan</label>
                </div>
            </div>
            <div class="tax-form-actions admin-resource-form-actions"><button class="btn" type="submit">Simpan dokumen</button></div>
        </form>
        </div>
    </section>
    <section class="card admin-resource-card">
        <div class="admin-resource-card-head"><div><h2>Dokumen karyawan</h2><p>Kelola status publikasi serta arsip dokumen pajak.</p></div><span class="admin-resource-count">{{ $documents->total() }} dokumen</span></div>
        <div class="admin-resource-table-wrap"><table class="admin-resource-table"><thead><tr><th>Pemilik</th><th>Tahun</th><th>Dokumen</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
            @forelse ($documents as $document)
                <tr>
                    <td><strong>{{ $document->user->name }}</strong><small>{{ $document->user->email }}</small></td>
                    <td>{{ $document->tax_year }}</td>
                    <td>{{ $document->title }}<small>{{ $document->description }}</small></td>
                    <td class="admin-resource-actions">
                        @if ($document->archived_at)
                            <span class="badge-md rounded-full text-xs font-medium bg-lighterror text-error">Diarsipkan</span>
                        @elseif ($document->visible_at)
                            <span class="badge-md rounded-full text-xs font-medium bg-lightsuccess text-success">Sudah diterbitkan</span>
                        @else
                            <form method="post" action="{{ route('admin.tax-documents.publish', $document) }}">@csrf @method('PATCH')<button class="btn text-xs py-1 px-2" type="submit">Terbitkan</button></form>
                        @endif
                        @if (! $document->archived_at) · <a class="text-primary hover:text-primaryemphasis" href="{{ route('admin.tax-documents.file', $document) }}">Lihat PDF</a>@endif
                    </td>
                    <td class="admin-resource-actions">
                        @if (! $document->archived_at)
                            <form method="post" action="{{ route('admin.tax-documents.update', $document) }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-popup-edit data-dialog-title="Ubah dokumen SPT · {{ $document->title }}">
                                @csrf @method('PUT')
                                <label>Karyawan
                                    <select class="form-control" name="user_id" required>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" @selected($document->user_id === $employee->id)>{{ $employee->name }} · {{ $employee->email }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Tahun pajak<input class="form-control" type="number" min="2000" max="2100" name="tax_year" value="{{ $document->tax_year }}" required></label>
                                <label class="md:col-span-2">Nama dokumen<input class="form-control" name="title" value="{{ $document->title }}" maxlength="160" required></label>
                                <label class="md:col-span-2">Keterangan<textarea class="form-control" name="description" rows="3">{{ $document->description }}</textarea></label>
                                <label class="md:col-span-2">Ganti PDF (opsional)<input class="form-control" type="file" name="document" accept="application/pdf"></label>
                                <label class="md:col-span-2"><input type="checkbox" name="publish_now" value="1" @checked($document->visible_at)> Tampilkan pada akun karyawan</label>
                                <div class="md:col-span-2"><button class="btn" type="submit">Simpan perubahan</button></div>
                            </form>
                            <form method="post" action="{{ route('admin.tax-documents.archive', $document) }}" data-confirm-action="archive">
                                @csrf @method('PATCH')
                                <button class="btn bg-lighterror text-error text-xs py-1 px-2" type="submit">Arsipkan</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.tax-documents.restore', $document) }}" data-confirm-action="restore">
                                @csrf @method('PATCH')
                                <button class="btn bg-lightprimary text-primary text-xs py-1 px-2" type="submit">Pulihkan</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="admin-resource-empty">Belum ada dokumen SPT.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="admin-resource-pagination flex justify-end">{{ $documents->links() }}</div>
    </section>
    </div>
@endsection
