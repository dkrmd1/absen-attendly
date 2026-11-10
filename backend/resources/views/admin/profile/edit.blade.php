@extends('admin.layouts.app')

@section('title', 'Pengaturan Profil')
@section('heading', 'Pengaturan Profil')
@section('breadcrumb', 'Profil')
@section('page-description', 'Kelola informasi profil dan keamanan akun Anda.')

@section('content')
    <div>
        <section class="card admin-profile-card">
            <div class="mb-6 flex items-center gap-4 border-b border-border pb-5">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-lightprimary text-xl font-semibold text-primary">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-dark dark:text-white">{{ $user->name }}</h2>
                    <p class="text-sm text-bodytext">{{ $user->email }}</p>
                </div>
            </div>

            <form method="post" action="{{ route('admin.profile.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')
                <div>
                    <h3 class="text-base font-semibold text-dark dark:text-white">Informasi profil</h3>
                    <p class="mt-1 text-sm text-bodytext">Nama dan email ini digunakan pada akun Attendly Anda.</p>
                    <div class="admin-profile-grid mt-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-link" for="profile-name">Nama lengkap</label>
                            <input class="form-control" id="profile-name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-link" for="profile-email">Alamat email</label>
                            <input class="form-control" id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" required>
                        </div>
                    </div>
                </div>

                <div id="account" class="border-t border-border pt-5">
                    <h3 class="text-base font-semibold text-dark dark:text-white">Ganti kata sandi</h3>
                    <p class="mt-1 text-sm text-bodytext">Pengaturan Keamanan Akun. Kosongkan kata sandi baru jika tidak ingin menggantinya.</p>
                    <div class="admin-profile-grid mt-4">
                        <div class="admin-profile-field-wide">
                            <label class="mb-1 block text-sm font-medium text-link" for="current-password">Kata sandi saat ini</label>
                            <input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-link" for="new-password">Kata sandi baru</label>
                            <input class="form-control" id="new-password" name="password" type="password" minlength="12" autocomplete="new-password">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-link" for="new-password-confirmation">Konfirmasi kata sandi baru</label>
                            <input class="form-control" id="new-password-confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-border pt-5">
                    <button class="btn" type="submit">Simpan perubahan</button>
                </div>
            </form>
        </section>
    </div>
@endsection
