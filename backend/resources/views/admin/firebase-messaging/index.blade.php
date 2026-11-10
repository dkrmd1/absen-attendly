@extends('admin.layouts.app')

@section('title', 'Notifikasi Firebase')
@section('heading', 'Notifikasi Firebase')
@section('breadcrumb', 'Notifikasi Firebase')
@section('page-description', 'Atur Firebase Cloud Messaging untuk notifikasi push di aplikasi Attendly.')

@push('styles')
    <style>
        .fcm-settings-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(280px,.85fr);align-items:start;gap:24px}
        .fcm-settings-card,.fcm-status-card{min-width:0;overflow:hidden;padding:0}
        .fcm-card-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;padding:24px;border-bottom:1px solid var(--color-bordergray)}
        .fcm-card-heading h2,.fcm-status-heading h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700}
        .fcm-card-heading p,.fcm-status-heading p{margin:6px 0 0;color:var(--color-bodytext);font-size:.875rem;line-height:1.6}
        .fcm-state-pill{display:inline-flex;flex-shrink:0;align-items:center;gap:8px;border:1px solid var(--color-bordergray);border-radius:999px;padding:7px 12px;color:var(--color-bodytext);font-size:.75rem;font-weight:700}
        .fcm-state-pill::before{width:8px;height:8px;border-radius:50%;background:var(--color-bodytext);content:""}
        .fcm-state-pill.is-ready{border-color:var(--color-lightsuccess);background:var(--color-lightsuccess);color:var(--color-success)}
        .fcm-state-pill.is-ready::before{background:var(--color-success)}
        .fcm-settings-body{display:grid;gap:24px;padding:24px}
        .fcm-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
        .fcm-field{display:grid;min-width:0;align-content:start;gap:8px;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .fcm-field .form-control{width:100%;min-width:0}
        .fcm-field-hint{color:var(--color-bodytext);font-size:.75rem;font-weight:400;line-height:1.5}
        .fcm-credentials{border:1px solid var(--color-bordergray);border-radius:var(--radius-md);background:var(--color-lightgray);padding:18px}
        .fcm-credentials-title{margin:0 0 5px;color:var(--color-dark);font-size:.9375rem;font-weight:700}
        .fcm-credentials-copy{margin:0 0 14px;color:var(--color-bodytext);font-size:.8125rem;line-height:1.55}
        .fcm-credentials .form-control{width:100%;min-width:0;background:var(--color-white)}
        .fcm-credential-status{display:grid;gap:12px;margin-top:14px}
        .fcm-enabled-option{display:flex;align-items:flex-start;gap:12px;border:1px solid var(--color-bordergray);border-radius:var(--radius-md);padding:16px}
        .fcm-enabled-option input{flex:0 0 auto;margin-top:4px}
        .fcm-enabled-option strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:700}
        .fcm-enabled-option span span{display:block;margin-top:4px;color:var(--color-bodytext);font-size:.8125rem;line-height:1.55}
        .fcm-form-actions{display:flex;flex-wrap:wrap;gap:12px}
        .fcm-status-heading{padding:22px 22px 18px;border-bottom:1px solid var(--color-bordergray)}
        .fcm-status-list{display:grid;padding:4px 22px}
        .fcm-status-row{display:grid;gap:5px;border-bottom:1px solid var(--color-bordergray);padding:16px 0}
        .fcm-status-row:last-child{border-bottom:0}
        .fcm-status-row dt{color:var(--color-bodytext);font-size:.8125rem}
        .fcm-status-row dd{margin:0;color:var(--color-dark);font-size:.9375rem;font-weight:700;overflow-wrap:anywhere}
        .fcm-status-steps{margin:0 22px 20px;border-top:1px solid var(--color-bordergray);padding-top:18px}
        .fcm-status-steps h3{margin:0;color:var(--color-dark);font-size:.875rem;font-weight:700}
        .fcm-status-steps ol{display:grid;gap:10px;margin:12px 0 0;padding-left:20px;color:var(--color-bodytext);font-size:.8125rem;line-height:1.55}
        .fcm-security-note{margin:0 22px 22px;border-radius:var(--radius-md);background:var(--color-lightinfo);padding:12px;color:var(--color-info);font-size:.75rem;line-height:1.55}
        html.dark .fcm-credentials .form-control{border-color:var(--color-darkborder);background-color:var(--color-darkgray);color:var(--color-white);color-scheme:dark}
        @media(max-width:1100px){.fcm-settings-layout{grid-template-columns:minmax(0,1fr)}}
        @media(max-width:640px){
            .fcm-settings-layout{gap:16px}
            .fcm-card-heading{flex-direction:column;gap:14px;padding:18px}
            .fcm-settings-body{gap:18px;padding:18px}
            .fcm-field-grid{grid-template-columns:minmax(0,1fr);gap:16px}
            .fcm-credentials{padding:14px}
            .fcm-form-actions{display:grid}
            .fcm-form-actions .btn{width:100%}
            .fcm-status-heading{padding:18px 18px 14px}
            .fcm-status-list{padding-right:18px;padding-left:18px}
            .fcm-status-steps{margin-right:18px;margin-left:18px}
            .fcm-security-note{margin-right:18px;margin-left:18px}
        }
    </style>
@endpush

@section('content')
    @php
        $isConfigured = $settings?->enabled && $settings?->service_account;
        $isReady = extension_loaded('sodium') && $isConfigured;
        $statusLabel = ! $isConfigured ? 'Belum aktif' : ($isReady ? 'Siap mengirim' : 'Aktifkan sodium');
    @endphp

    <div class="fcm-settings-layout">
        <section class="card fcm-settings-card">
            <div class="fcm-card-heading">
                <div>
                    <h2>Pengaturan Firebase Cloud Messaging</h2>
                    <p>Hubungkan aplikasi Android dengan Firebase agar server dapat mengirim notifikasi push.</p>
                </div>
                <span class="fcm-state-pill {{ $isReady ? 'is-ready' : '' }}">{{ $statusLabel }}</span>
            </div>
            <div class="fcm-settings-body">
                @if (! extension_loaded('sodium'))
                    <div class="rounded-md border border-lightwarning bg-lightwarning p-4 text-warning" role="alert">
                        <strong class="mb-1 block">Pengiriman push belum dapat digunakan.</strong>
                        Ekstensi PHP <code>sodium</code> belum aktif. Aktifkan di <code>php.ini</code>, lalu restart Apache.
                    </div>
                @endif

                <form method="post" action="{{ route('admin.firebase-messaging.update') }}" enctype="multipart/form-data" class="grid gap-6">
                    @csrf
                    @method('put')
                    <div class="fcm-field-grid">
                        <label class="fcm-field">
                            <span>Firebase project ID</span>
                            <input class="form-control" name="project_id" value="{{ old('project_id', $settings?->project_id) }}" required maxlength="255" autocomplete="off">
                        </label>
                        <label class="fcm-field">
                            <span>Firebase app ID Android</span>
                            <input class="form-control" name="app_id" value="{{ old('app_id', $settings?->app_id) }}" required maxlength="255" autocomplete="off">
                        </label>
                        <label class="fcm-field">
                            <span>Firebase API key</span>
                            <input class="form-control" name="api_key" value="{{ old('api_key', $settings?->api_key) }}" required maxlength="255" autocomplete="off">
                        </label>
                        <label class="fcm-field">
                            <span>Messaging sender ID</span>
                            <input class="form-control" name="messaging_sender_id" value="{{ old('messaging_sender_id', $settings?->messaging_sender_id) }}" required maxlength="255" autocomplete="off">
                        </label>
                    </div>

                    <div class="fcm-credentials">
                        <h3 class="fcm-credentials-title">Kredensial server</h3>
                        <p class="fcm-credentials-copy">Unggah private key service account Firebase. File akan dienkripsi dan tidak ditampilkan kembali.</p>
                        <label class="fcm-field">
                            <span>Service account JSON</span>
                            <input class="form-control" type="file" name="service_account" accept=".json,application/json">
                        </label>
                        <p class="fcm-field-hint mt-2">Jangan unggah file ini ke APK, chat, atau repository.</p>
                        @if ($settings?->service_account)
                            <div class="fcm-credential-status">
                                <span class="text-sm text-success">Service account sudah tersimpan secara terenkripsi.</span>
                                <label class="flex items-center gap-2 text-sm text-error">
                                    <input type="checkbox" name="remove_service_account" value="1">
                                    Hapus kredensial tersimpan
                                </label>
                            </div>
                        @else
                            <p class="fcm-field-hint mt-3">Belum ada service account yang tersimpan.</p>
                        @endif
                    </div>

                    <label class="fcm-enabled-option">
                        <input class="mt-1" type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings?->enabled))>
                        <span>
                            <strong>Aktifkan pengiriman notifikasi push</strong>
                            <span>Notifikasi inbox Attendly tetap tersimpan walaupun push tidak aktif atau gagal dikirim.</span>
                        </span>
                    </label>

                    <div class="fcm-form-actions">
                        <button class="btn btn-primary" type="submit">Simpan pengaturan</button>
                        @if ($isReady)
                            <button class="btn" type="submit" form="firebase-push-test">Kirim notifikasi uji</button>
                        @endif
                    </div>
                </form>

                @if ($isReady)
                    <form id="firebase-push-test" method="post" action="{{ route('admin.firebase-messaging.test') }}" class="hidden">
                        @csrf
                    </form>
                @endif
            </div>
        </section>

        <aside class="card fcm-status-card">
            <div class="fcm-status-heading">
                <h2>Status integrasi</h2>
                <p>Ringkasan koneksi Firebase dan perangkat yang sudah terdaftar.</p>
            </div>
            <dl class="fcm-status-list">
                <div class="fcm-status-row">
                    <dt>Status</dt>
                    <dd>{{ $statusLabel }}</dd>
                </div>
                <div class="fcm-status-row">
                    <dt>Perangkat terdaftar</dt>
                    <dd>{{ $deviceCount }} perangkat</dd>
                </div>
                <div class="fcm-status-row">
                    <dt>Firebase project</dt>
                    <dd>{{ $settings?->project_id ?: 'Belum diatur' }}</dd>
                </div>
            </dl>
            <div class="fcm-status-steps">
                <h3>Langkah konfigurasi</h3>
                <ol>
                    <li>Buat Firebase project dan daftarkan Android app <code>id.co.attendly.mobile</code>.</li>
                    <li>Salin project ID, app ID, API key, dan sender ID ke formulir ini.</li>
                    <li>Buat service account di Firebase/Google Cloud dan unggah file JSON-nya.</li>
                    <li>Aktifkan FCM, simpan, lalu uji setelah perangkat login ke Attendly.</li>
                </ol>
            </div>
            <p class="fcm-security-note">API key dan app ID adalah konfigurasi aplikasi. Service account berisi private key dan tidak pernah ditampilkan kembali.</p>
        </aside>
    </div>
@endsection
