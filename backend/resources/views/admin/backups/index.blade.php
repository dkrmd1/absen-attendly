@extends('admin.layouts.app')

@section('title', 'Backup & pemulihan')
@section('heading', 'Backup & pemulihan')
@section('breadcrumb', 'Backup & pemulihan')

@push('styles')
    <style>
        .backup-page{display:grid;gap:20px}
        .backup-card{min-width:0;overflow:hidden;padding:0}
        .backup-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;padding:24px;border-bottom:1px solid var(--color-bordergray)}
        .backup-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700;letter-spacing:-.01em}
        .backup-head p{max-width:680px;margin:7px 0 0;color:var(--color-bodytext);font-size:.875rem;line-height:1.6}
        .backup-create{flex-shrink:0}
        .backup-create button{min-height:42px}
        .backup-notice{display:flex;align-items:flex-start;gap:12px;margin:20px 24px 0;border:1px solid var(--color-lightwarning);border-radius:var(--radius-md);background:var(--color-lightwarning);padding:14px 16px;color:var(--color-warningemphasis);font-size:.8125rem;line-height:1.6}
        .backup-notice strong{display:block;margin-bottom:2px}
        .backup-notice p{margin:0}
        .backup-retention{display:flex;flex-wrap:wrap;gap:10px;padding:20px 24px 0}
        .backup-pill{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-white);padding:7px 11px;color:var(--color-bodytext);font-size:.75rem;font-weight:600}
        .backup-pill strong{color:var(--color-dark)}
        .backup-table-wrap{overflow-x:auto;margin-top:18px}
        .backup-table{min-width:690px}
        .backup-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.045em;text-transform:uppercase}
        .backup-table td{padding:15px 16px;vertical-align:middle}
        .backup-table tbody tr:last-child td{border-bottom:0}
        .backup-file{display:flex;align-items:center;gap:10px;min-width:280px}
        .backup-file-icon{display:grid;width:38px;height:38px;flex:0 0 38px;place-items:center;border-radius:var(--radius-md);background:var(--color-lightprimary);color:var(--color-primary);font-size:19px}
        .backup-file strong{display:block;color:var(--color-dark);font-size:.8125rem;font-weight:600;overflow-wrap:anywhere}
        .backup-file small{margin-top:3px;color:var(--color-bodytext);font-size:.72rem}
        .backup-status{display:inline-flex;align-items:center;gap:6px;border-radius:999px;background:var(--color-lightsuccess);padding:6px 9px;color:var(--color-success);font-size:.72rem;font-weight:600;white-space:nowrap}
        .backup-empty{padding:46px 20px!important;text-align:center!important}
        .backup-empty strong{display:block;color:var(--color-dark);font-size:.9375rem;font-weight:600}
        .backup-empty span{display:block;margin-top:5px;color:var(--color-bodytext);font-size:.8125rem}
        .backup-restore{margin:22px 24px 24px;border:1px solid var(--color-bordergray);border-radius:var(--radius-md);padding:18px}
        .backup-restore h3{margin:0;color:var(--color-dark);font-size:.9375rem;font-weight:700}
        .backup-restore p{margin:7px 0 0;color:var(--color-bodytext);font-size:.8125rem;line-height:1.6}
        .backup-restore code{overflow-wrap:anywhere;color:var(--color-primary);font-size:.78rem}
        @media(max-width:720px){
            .backup-head{align-items:stretch;flex-direction:column;padding:18px 16px}
            .backup-head h2{font-size:1rem}
            .backup-create button{width:100%}
            .backup-notice{margin:16px 16px 0}
            .backup-retention{padding:16px 16px 0}
            .backup-restore{margin:18px 16px}
        }
    </style>
@endpush

@section('content')
    <div class="backup-page">
        <section class="card backup-card">
            <header class="backup-head">
                <div>
                    <h2>Backup data Attendly</h2>
                    <p>Buat salinan database MySQL beserta berkas unggahan. Arsip dienkripsi sebelum disimpan dan hanya dapat diunduh oleh administrator.</p>
                </div>
                <form class="backup-create" method="post" action="{{ route('admin.backups.store') }}"
                    onsubmit="return confirm('Buat backup terenkripsi sekarang? Proses dapat memerlukan waktu beberapa menit.')">
                    @csrf
                    <button class="btn" type="submit">Buat backup sekarang</button>
                </form>
            </header>

            <div class="backup-notice" role="note">
                <span aria-hidden="true">!</span>
                <div>
                    <strong>Penyimpanan lokal sementara</strong>
                    <p>Backup berada di server yang sama. Jika server atau disk rusak, backup dapat ikut hilang. Unduh salinan terenkripsi dan simpan di lokasi terpisah. Pemulihan memerlukan APP_KEY yang sama.</p>
                </div>
            </div>

            <div class="backup-retention" aria-label="Kebijakan backup">
                <span class="backup-pill"><strong>Jadwal</strong> Manual</span>
                <span class="backup-pill"><strong>Retensi</strong> {{ config('backups.retention_days', 7) }} hari</span>
                <span class="backup-pill"><strong>Isi</strong> Database &amp; unggahan</span>
                <span class="backup-pill"><strong>Enkripsi</strong> AES-256-GCM</span>
            </div>

            <div class="backup-table-wrap">
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th scope="col">File backup</th>
                            <th scope="col">Dibuat</th>
                            <th scope="col">Ukuran</th>
                            <th scope="col">Status</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($backups as $backup)
                            <tr>
                                <td>
                                    <div class="backup-file">
                                        <span class="backup-file-icon" aria-hidden="true">⬡</span>
                                        <span>
                                            <strong>{{ $backup['filename'] }}</strong>
                                            <small>Arsip terenkripsi · tidak bisa dibuka langsung</small>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $backup['modified_at']->format('d/m/Y H:i') }}</td>
                                <td>{{ \Illuminate\Support\Number::fileSize($backup['size']) }}</td>
                                <td><span class="backup-status">Tersimpan</span></td>
                                <td><a class="btn" href="{{ route('admin.backups.download', $backup['filename']) }}">Unduh</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td class="backup-empty" colspan="5">
                                    <strong>Belum ada backup</strong>
                                    <span>Pilih “Buat backup sekarang” untuk membuat salinan pertama.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <aside class="backup-restore">
                <h3>Pemulihan data</h3>
                <p>Pemulihan hanya melalui terminal server dan membutuhkan konfirmasi karena database aktif akan ditimpa. Hentikan sementara aplikasi/worker, lalu jalankan:</p>
                <p><code>php artisan attendly:backup-restore NAMA_FILE.enc --confirm</code></p>
                <p>Pastikan file backup sudah tersedia di <code>storage/app/backups</code> dan APP_KEY tidak berubah. Pembersihan retensi berjalan lewat Laravel Scheduler; pada Windows, atur Task Scheduler menjalankan <code>php artisan schedule:run</code> setiap menit. Proses pembuatan backup tetap manual.</p>
            </aside>
        </section>
    </div>
@endsection
