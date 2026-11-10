@extends('admin.layouts.app')

@section('title', 'Berita dan pengumuman')
@section('heading', 'Berita dan pengumuman')
@section('breadcrumb', 'Informasi perusahaan')

@section('content')
    <div class="admin-resource-page">
    <div class="admin-resource-toolbar"><p>Buat dan perbarui informasi yang ditampilkan kepada karyawan.</p><a class="btn" href="#tambah-informasi">＋ Tulis informasi</a></div>
    <section class="card admin-resource-card modal-form" id="tambah-informasi">
        <div class="admin-resource-card-head panel-head"><div><h2>Tulis informasi perusahaan</h2><p>Informasi yang diterbitkan akan tampil di aplikasi karyawan.</p></div></div>
        <div class="admin-resource-card-body">
        <form method="post" action="{{ route('admin.announcements.store') }}" class="announcement-create-form admin-resource-form">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 announcement-create-grid">
                <div>
                    <label class="block mb-1 text-sm font-medium text-link" for="announcement-category">Kategori</label>
                    <select class="form-control" id="announcement-category" name="category" required><option value="berita">Berita</option><option value="pengumuman">Pengumuman</option></select>
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1 text-sm font-medium text-link" for="announcement-title">Judul</label>
                    <input class="form-control" id="announcement-title" name="title" maxlength="180" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-1 text-sm font-medium text-link" for="announcement-body">Isi informasi</label>
                    <textarea class="form-control" id="announcement-body" name="body" rows="6" maxlength="10000" required></textarea>
                </div>
                <div class="md:col-span-2 announcement-publish-option">
                    <label><input type="checkbox" name="publish_now" value="1"> Terbitkan sekarang <span>Draft dapat diterbitkan kapan saja.</span></label>
                </div>
            </div>
            <div class="announcement-form-actions admin-resource-form-actions"><button class="btn" type="submit">Simpan informasi</button></div>
        </form>
        </div>
    </section>
    <section class="card admin-resource-card">
        <div class="admin-resource-card-head"><div><h2>Semua informasi</h2><p>Berita dan pengumuman yang tersedia di aplikasi karyawan.</p></div><span class="admin-resource-count">{{ $announcements->total() }} informasi</span></div>
        <div class="admin-resource-table-wrap"><table class="admin-resource-table"><thead><tr><th>Kategori</th><th>Judul & isi</th><th>Status</th><th>Waktu terbit</th><th>Tindakan</th></tr></thead><tbody>
            @forelse ($announcements as $announcement)
                <tr>
                    <td>{{ $announcement->category === 'berita' ? 'Berita' : 'Pengumuman' }}</td>
                    <td><strong>{{ $announcement->title }}</strong><small>{{ \Illuminate\Support\Str::limit($announcement->body, 120) }}</small></td>
                    <td><span class="badge-md rounded-full text-xs font-medium {{ $announcement->archived_at ? 'bg-lighterror text-error' : ($announcement->published_at ? 'bg-lightsuccess text-success' : 'bg-lightwarning text-warning') }}">{{ $announcement->archived_at ? 'Diarsipkan' : ($announcement->published_at ? 'Terbit' : 'Draft') }}</span></td>
                    <td>{{ $announcement->published_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                    <td class="admin-resource-actions">
                        @if (! $announcement->archived_at)
                            <form method="post" action="{{ route('admin.announcements.update', $announcement) }}" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-popup-edit data-dialog-title="Ubah informasi · {{ $announcement->title }}">
                                @csrf @method('PATCH')
                                <label>Kategori<select class="form-control" name="category"><option value="berita" @selected($announcement->category === 'berita')>Berita</option><option value="pengumuman" @selected($announcement->category === 'pengumuman')>Pengumuman</option></select></label>
                                <label class="md:col-span-2">Judul<input class="form-control" name="title" value="{{ $announcement->title }}" maxlength="180" required></label>
                                <label class="md:col-span-2">Isi informasi<textarea class="form-control" name="body" rows="6" maxlength="10000" required>{{ $announcement->body }}</textarea></label>
                                <label class="md:col-span-2"><input type="checkbox" name="publish_now" value="1" @checked($announcement->published_at)> Terbitkan informasi</label>
                                <div class="md:col-span-2"><button class="btn" type="submit">Simpan perubahan</button></div>
                            </form>
                            <form method="post" action="{{ route('admin.announcements.archive', $announcement) }}" data-confirm-action="archive">
                                @csrf @method('PATCH')
                                <button class="btn bg-lighterror text-error text-xs py-1 px-2" type="submit">Arsipkan</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.announcements.restore', $announcement) }}" data-confirm-action="restore">
                                @csrf @method('PATCH')
                                <button class="btn bg-lightprimary text-primary text-xs py-1 px-2" type="submit">Pulihkan</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="admin-resource-empty">Belum ada berita atau pengumuman.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="admin-resource-pagination flex justify-end">{{ $announcements->links() }}</div>
    </section>
    </div>
@endsection
