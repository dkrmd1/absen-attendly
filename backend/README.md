# Attendly API

Backend REST untuk aplikasi absensi Flutter. Laravel 12, PHP 8.2+, MySQL, dan
token API Sanctum.

## Persiapan lokal (XAMPP)

1. Pastikan MySQL aktif dan database yang tercantum di `backend/.env` tersedia.
   Workspace ini sebelumnya sudah memakai database `absen`; pertahankan
   konfigurasi itu jika database tersebut yang sudah dimigrasikan.
2. Salin `.env.example` menjadi `.env` hanya jika belum ada, lalu isi kredensial
   database lokal. Jangan mengganti `.env` yang sudah berfungsi.
3. Terapkan migrasi jika belum dijalankan. Migrasi menambah master departemen,
   lokasi kerja, cabang utama karyawan, penugasan sementara, shift kerja,
   metadata peninjauan absensi, aktivitas, payroll, dokumen SPT, dan berita.
   Migrasi juga menambahkan saldo cuti tahunan, kalender hari libur perusahaan,
   group kerja, snapshot struktur pengajuan, serta riwayat persetujuan.
   Data akun lama tetap menjadi karyawan; admin perlu menetapkan departemen,
   cabang, dan bila diperlukan shift atau group mereka.

```powershell
cd backend
php artisan migrate
```

4. Jalankan:

```powershell
cd backend
composer install
php artisan serve --host=0.0.0.0 --port=8000
```

Server API lokal tersedia di `http://127.0.0.1:8000`.

### Dashboard admin

Buat akun admin dari folder `backend`. Kata sandi diminta secara tersembunyi
dan minimal 12 karakter:

```powershell
php artisan attendly:create-admin
```

Jika dashboard menampilkan error password bukan Bcrypt, kemungkinan akun lama
menyimpan password dengan format hash yang tidak sesuai konfigurasi saat ini.
Atur ulang password akun admin yang sudah ada (email akun harus ber-role admin):

```powershell
php artisan attendly:reset-admin-password admin@perusahaan.co.id
```

Password baru dan konfirmasinya diminta secara tersembunyi. Login akan
menampilkan pesan pemulihan alih-alih halaman error jika format password
akun lama tidak kompatibel. Jangan mengubah password langsung di database.

### Play Integrity (opsional, Android)

Play Integrity memeriksa token pada server Google sebelum check-in/check-out,
memastikan token terikat pada aksi, akun, koordinat, dan hash selfie (jika ada),
masih baru, aplikasi dikenali Play Store, serta perangkat memenuhi
`MEETS_DEVICE_INTEGRITY`. Fitur ini
nonaktif secara default dan hanya dapat diaktifkan setelah aplikasi tersedia
melalui Google Play (minimal internal testing) dan project ditautkan:

1. Aktifkan Play Integrity API di Google Cloud, lalu tautkan project tersebut
   ke aplikasi di Play Console → **App integrity / Play Integrity API**.
2. Buat service account untuk backend, beri akses API Play Console yang
   diperlukan, lalu buat key JSON. Simpan key di luar repository, misalnya
   `backend/storage/app/private/play-integrity-service-account.json`. Jangan
   commit atau mengirim file key ke chat.
3. Isi `PLAY_INTEGRITY_ENABLED=true` di `backend/.env`. Sesuaikan package name
   jika application ID berubah, dan isi `PLAY_INTEGRITY_SERVICE_ACCOUNT_PATH`
   bila file key berada di lokasi lain.
4. Build aplikasi Android dengan nomor project Cloud yang ditautkan:

```powershell
flutter build apk --dart-define=PLAY_INTEGRITY_CLOUD_PROJECT_NUMBER=123456789012
```

Server memverifikasi token langsung ke Google dan menolak permintaan jika
verdict invalid, kedaluwarsa, tidak terikat ke payload, tidak mengenali app,
atau perangkat tidak memenuhi integrity. Jika layanan Google/configuration
tidak tersedia setelah fitur diaktifkan, absensi ditolak sementara (fail
closed). Mulai dengan internal testing di Play Store; APK debug/sideload dan
preview web tidak akan memenuhi verdict Play-recognized. Jangan aktifkan
pengaturan ini di server yang melayani aplikasi sebelum key dan build Play
testing siap. Implementasi saat ini khusus Android; saat diaktifkan, aplikasi
iOS belum dapat mengirim attestation dan absensinya juga akan ditolak. Siapkan
Apple App Attest sebagai jalur iOS sebelum mengaktifkan kebijakan ini untuk
pengguna iOS.

Buka `http://127.0.0.1:8000/admin/login` untuk masuk. Dashboard mencakup
ringkasan kehadiran, daftar dan pembuatan akun karyawan, riwayat absensi, serta
persetujuan pengajuan. Admin mengelola unit organisasi di **Departemen** dan **Group kerja**, serta
tempat kerja/geofence di **Lokasi kerja** secara terpisah. Ketua group dipilih
dari karyawan dalam departemen; penetapan ketua otomatis memberi role `group_lead`.
Kepala divisi ditetapkan pada departemen dan diberi role `division_head`.
Role `hr` dan `director` dapat diberikan melalui daftar karyawan. Departemen
dan group dipakai untuk cakupan persetujuan; GPS mengikuti lokasi/cabang dan
penugasan, bukan departemen. Titik
koordinat dan radius harus diisi dengan lokasi nyata sebelum absensi GPS dapat
dilakukan. Akun karyawan biasa tidak dapat membuka portal admin.
Form lokasi kerja menyediakan peta untuk memilih/menggeser pin, mengisi
koordinat manual, dan melihat radius geofence. Peta prototipe memakai tile
publik OpenStreetMap; untuk penggunaan produksi, pilih penyedia tile sesuai
kebijakan penggunaan, kapasitas, dan kebutuhan layanan.
Pengaturan kantor tunggal versi lama tidak lagi digunakan untuk validasi absensi;
data lokasi tersebut tidak otomatis dijadikan cabang.

Admin mengatur jam masuk/pulang, hari kerja, dan toleransi melalui menu
**Shift kerja**, lalu menetapkan shift pada akun karyawan. Shift tanpa
penetapan tetap diperbolehkan. Waktu terlambat dihitung oleh server berdasarkan
jam mulai dan toleransi shift, lalu snapshot jadwal disimpan bersama absensi.
Jadwal yang sama ditampilkan pada beranda aplikasi mobile.

Saat check-in, aplikasi meminta selfie sebagai bukti kehadiran tanpa
pencocokan wajah. Jika karyawan memilih lanjut tanpa selfie, absensi onsite
masuk antrean review manual. Foto disimpan pada storage privat, hanya dapat
dilihat HR dan admin, serta dihapus otomatis setelah 90 hari. Jalankan scheduler
Laravel agar penghapusan berlangsung setiap hari:

```powershell
cd backend
php artisan schedule:work
```

Di server produksi, jalankan scheduler sesuai mekanisme sistem operasi.

### Notifikasi absensi

Hasil check-in/check-out ditampilkan langsung kepada karyawan di aplikasi.
Check-in yang melewati jam shift dan toleransi mengirim peringatan kepada
atasan langsung. Setiap lima menit, scheduler memeriksa karyawan yang memiliki
shift aktif; ringkasan belum check-in dikirim sekali per atasan dan shift,
setelah 15 menit tambahan dari jam mulai dan toleransi. Hari non-kerja,
absensi yang sudah tercatat, dan izin/sakit/cuti yang sudah disetujui
dikecualikan. Jika atasan langsung tidak tersedia, pemberitahuan diteruskan ke
HR, lalu admin.

Jalankan Laravel Scheduler secara terus-menerus agar pemeriksaan ini berjalan.
Untuk pengembangan lokal, gunakan `php artisan schedule:work`; untuk Windows
Task Scheduler, jalankan `php artisan schedule:run` setiap menit.

### Role dan alur persetujuan

Admin mengelola akun, departemen, group, lokasi, shift, serta konfigurasi
organisasi. HR memiliki akses operasional ke absensi, aktivitas, saldo cuti,
payroll, SPT, pengumuman, dan menjadi peninjau akhir pengajuan. Direktur dapat
meninjau pengajuan eksekutif. Ketua group hanya melihat pengajuan dalam group
yang dipimpinnya; kepala divisi hanya melihat pengajuan dalam departemennya.
Semua role portal memakai halaman login admin, tetapi menu dan izin dibatasi
sesuai role.

Alur umum: karyawan dalam group → ketua group → kepala divisi → HR; karyawan
tanpa group memulai dari kepala divisi. Jika group tidak memiliki ketua yang
aktif atau departemen tidak memiliki kepala divisi yang aktif, tahap tersebut
dilewati agar pengajuan tidak macet. Pengajuan kepala divisi memerlukan
persetujuan direktur sebelum HR; pengajuan HR diputuskan direktur agar tidak
menyetujui pengajuan sendiri; pengajuan direktur diputuskan HR. Pengaju tidak
dapat meninjau pengajuannya sendiri. Setiap keputusan tersimpan dalam riwayat
review, dan pengajuan menyimpan snapshot departemen, group, serta role saat
diajukan agar perubahan organisasi tidak mengubah cakupan historis.

HR mengatur jatah cuti tahunan per karyawan dan tahun melalui menu **Saldo
cuti**. Hari libur perusahaan dikelola di halaman yang sama dan dikecualikan
dari perhitungan. Pemakaian hanya dihitung dari pengajuan cuti tahunan yang
telah disetujui final oleh HR atau peninjau akhir yang berwenang. Hari kerja
mengikuti shift karyawan; jika belum ada shift, sistem memakai Senin–Jumat. HR
tidak dapat menyetujui pengajuan cuti tahunan jika saldo tahun terkait belum
diatur atau tidak cukup. Karyawan melihat sisa saldo di aplikasi mobile.
Setelah menerapkan migrasi, atur jatah cuti dan kalender libur perusahaan
sebelum menyetujui pengajuan cuti tahunan.

Persetujuan perjalanan dinas dan diklat tatap muka membuat penugasan sementara
sesuai rentang tanggal dan lokasi yang dipilih peninjau akhir. Penugasan itu dipakai
menggantikan cabang utama untuk validasi GPS. Diklat online tidak meminta GPS
dan absensinya masuk antrean peninjauan HR. Rentang penugasan yang bertabrakan
ditolak.

### Membuat akun karyawan

Belum ada akun bawaan atau endpoint pendaftaran publik. Buat akun karyawan
pertama dari folder `backend` dengan perintah:

```powershell
php artisan attendly:create-employee
```

Nama, email, dan password akan diminta secara interaktif; password tidak
ditampilkan. Password minimal 12 karakter. Tidak ada endpoint pendaftaran
publik maupun akun demo bawaan.

### Menjalankan aplikasi mobile

Untuk preview lokal di Chrome:

```powershell
cd mobile
flutter run -d chrome --web-port=5000 --dart-define=API_BASE_URL=http://127.0.0.1:8000/api
```

Default API Flutter adalah `http://127.0.0.1:8000/api`. Untuk emulator Android,
jalankan API dengan `--host=0.0.0.0` lalu jalankan Flutter dengan
`--dart-define=API_BASE_URL=http://10.0.2.2:8000/api`. Koneksi HTTP hanya untuk
pengembangan lokal; aplikasi produksi harus memakai HTTPS.

## Endpoint

Endpoint kecuali `POST /api/login` memerlukan header
`Authorization: Bearer <access_token>`.

| Method | Path | Keterangan |
|---|---|---|
| `POST` | `/api/login` | Login email dan kata sandi |
| `POST` | `/api/logout` | Cabut token saat ini |
| `GET` | `/api/me` | Profil pengguna aktif |
| `GET` | `/api/attendance/today` | Absensi hari ini |
| `GET` | `/api/attendance/settings` | Lokasi dan kebutuhan GPS sesuai penugasan aktif |
| `GET` | `/api/attendances?from=YYYY-MM-DD&to=YYYY-MM-DD` | Riwayat pribadi (paginated) |
| `POST` | `/api/attendance/check-in` | Catat jam masuk dari waktu server |
| `POST` | `/api/attendance/check-out` | Catat jam pulang dari waktu server |
| `GET` | `/api/leave-requests` | Daftar pengajuan pribadi (paginated) |
| `POST` | `/api/leave-requests` | Buat pengajuan izin, sakit, cuti, lembur, perjalanan dinas, atau diklat |
| `GET` | `/api/leave-requests/{id}/attachment` | Unduh lampiran milik sendiri |
| `GET` | `/api/leave-balance` | Saldo cuti tahunan akun aktif |
| `GET` | `/api/activity-reports` | Daftar aktivitas harian pribadi |
| `POST` | `/api/activity-reports` | Kirim aktivitas harian untuk ditinjau |
| `GET` | `/api/payroll` | Daftar payroll milik sendiri |
| `GET` | `/api/payroll/{id}/payslip` | Unduh slip gaji milik sendiri |
| `GET` | `/api/tax-documents` | Daftar dokumen SPT yang diterbitkan untuk diri sendiri |
| `GET` | `/api/tax-documents/{id}/file` | Unduh dokumen SPT milik sendiri |
| `GET` | `/api/announcements` | Berita dan pengumuman yang telah diterbitkan |

Pengajuan menerima `type` (`izin`, `sakit`, `cuti`, `lembur`,
`perjalanan_dinas`, atau `diklat`), `start_date`, `end_date`, dan `reason`.
Kategori sakit tanpa surat dokter tidak mewajibkan lampiran; kategori sakit
dengan surat dokter wajib menyertakan PDF atau gambar. Perjalanan dinas
memerlukan `details.destination`, diklat memerlukan `details.institution`, dan
lembur memerlukan waktu mulai/selesai serta deskripsi pekerjaan di `details`.
Lampiran pengajuan disimpan privat. Persetujuan mengikuti tahapan group,
departemen, direktur, dan HR sesuai role serta snapshot struktur organisasi.
Payroll dan dokumen SPT dikelola HR/admin, tetapi hanya pemilik akun yang dapat
melihat atau mengunduh dokumennya. Lampiran privat tidak disimpan di direktori
publik.

GPS diminta saat check-in/check-out tatap muka. Server menentukan lokasi
berdasarkan penugasan sementara yang disetujui dan sedang berlaku; jika tidak
ada, server memakai cabang utama karyawan. Koordinat harus dimasukkan admin
melalui **Lokasi kerja** dan absensi ditolak jika GPS hilang atau di luar radius.
Jangan mengarang koordinat. Validasi GPS membantu pembatasan lokasi tetapi
tidak menjamin perangkat bebas dari pemalsuan lokasi; untuk pemakaian produksi,
gunakan HTTPS dan tetap siapkan prosedur koreksi absensi.

## Pengujian

Feature tests memakai SQLite in-memory dan tidak menyentuh database MySQL:

```powershell
cd backend
php artisan test
```

Verifikasi selfie/face matching belum diaktifkan. Fitur biometrik perlu
persetujuan karyawan, alternatif bagi yang tidak dapat atau tidak menyetujui
verifikasi wajah, dan kebijakan penghapusan data sebelum dipakai di produksi.
