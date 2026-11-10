# Attendly

Prototipe aplikasi absensi Attendly: Flutter mobile dan API Laravel.

## Menjalankan aplikasi

Jalankan API Laravel dari terminal pertama:

```powershell
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

Setelah membuat akun karyawan, jalankan preview aplikasi di Chrome dari terminal
lain:

```powershell
cd mobile
flutter pub get
flutter run -d chrome --web-port=5000 --dart-define=API_BASE_URL=http://127.0.0.1:8000/api
```

Untuk emulator Android, gunakan `--dart-define=API_BASE_URL=http://10.0.2.2:8000/api`.

## Menguji

```powershell
cd mobile
flutter analyze
flutter test
```

## Backend API

Backend Laravel + Sanctum dan panduan database ada di [backend/README.md](backend/README.md).
Flutter kini terhubung ke API untuk login, check-in/out, riwayat, dan pengajuan.
Dashboard admin tersedia pada `/admin` setelah migrasi database dan pembuatan
akun administrator. Untuk menjalankan app, mulai Laravel dari `backend`, buat
akun karyawan, lalu jalankan Flutter dari `mobile`. Langkah lengkap ada di
[backend/README.md](backend/README.md).

Modul karyawan dipisahkan menjadi izin/cuti dan sakit, lembur, perjalanan
dinas, diklat, aktivitas, info gaji, dokumen SPT, serta berita/pengumuman.
Pengajuan ditinjau supervisor departemen lalu diputuskan admin; payroll dan
dokumen SPT hanya dapat dilihat pemilik akun.
