# Attendly Mobile

Aplikasi absensi karyawan Flutter untuk Android dan iOS, dengan target web
untuk preview prototipe.

Backend API Laravel harus aktif di `http://127.0.0.1:8000` dan akun karyawan
harus sudah dibuat. Lihat [panduan backend](../backend/README.md).

Jalankan preview browser dengan:

```powershell
flutter pub get
flutter run -d chrome --web-port=5000 --dart-define=API_BASE_URL=http://127.0.0.1:8000/api
```

Untuk emulator Android lokal, ganti alamat API ke
`http://10.0.2.2:8000/api` serta jalankan backend dengan `--host=0.0.0.0`.
Cleartext HTTP hanya diizinkan untuk Android debug. Token login disimpan
melalui secure storage.
GPS perangkat akan diminta sesuai cabang atau penugasan yang disetujui.
Sebelum check-in/pulang, aplikasi menampilkan peta OpenStreetMap, posisi GPS,
akurasi, dan radius lokasi kerja. Peta memakai tile publik OSM untuk prototipe;
sebelum trafik produksi, evaluasi kebijakan tile OSM dan gunakan penyedia tile
yang sesuai kebutuhan/SLA bila diperlukan. Peta membutuhkan koneksi internet.
Saat check-in, karyawan dapat mengirim selfie sebagai bukti absensi tanpa
pencocokan wajah; selfie disimpan privat maksimal 90 hari. Jika selfie tidak
dapat dikirim, absensi onsite masuk review manual. Pengajuan karyawan mendukung
izin dan cuti dengan kategori, sakit dengan atau tanpa surat dokter, lembur,
perjalanan dinas, dan diklat. Lampiran pengajuan bersifat opsional, kecuali
surat dokter wajib untuk kategori sakit dengan surat. Aktivitas harian,
informasi gaji, dokumen SPT, serta berita/pengumuman tersedia sebagai modul
terpisah; data gaji dan SPT hanya dapat diakses karyawan pemilik akun.

Absensi memerlukan koneksi ke server agar waktu, GPS, dan statusnya dapat
diverifikasi. Jika koneksi terputus saat pengiriman, aplikasi akan mencoba
memuat ulang status absensi sebelum meminta pengiriman ulang. Jangan menganggap
absensi offline sudah tercatat; pastikan status sudah tampil dari server.

Di Android/iOS, selfie hanya diambil melalui kamera depan; aplikasi tidak
menyediakan pilihan galeri. Preview web menolak unggahan gambar untuk selfie.
Android menolak absensi ketika layanan lokasi menandai koordinat sebagai mock
location. Opsi pengembang tidak dapat dikunci oleh aplikasi dan deteksi mock
location bukan jaminan anti-kecurangan mutlak, terutama pada perangkat yang
dimodifikasi atau di-root.

Play Integrity dapat diaktifkan di backend setelah aplikasi dipublikasikan ke
internal testing Google Play dan project Cloud ditautkan melalui Play Console.
Saat build Android, berikan nomor project dengan
`--dart-define=PLAY_INTEGRITY_CLOUD_PROJECT_NUMBER=<nomor-project>`. Backend
memverifikasi token ke Google dan menolak absensi bila verdict app/perangkat
tidak sesuai. Build sideload/debug dan preview web tidak dapat dipakai untuk
menguji verdict `PLAY_RECOGNIZED`. Lihat bagian Play Integrity di README
backend untuk konfigurasi server dan penyimpanan service-account key.
Implementasi attestation saat ini khusus Android; jika diwajibkan oleh backend,
aplikasi iOS juga perlu diintegrasikan dengan Apple App Attest.

## Firebase Cloud Messaging Android

Daftarkan aplikasi Android di Firebase dengan package name
`id.co.attendly.mobile`, lalu simpan `google-services.json` dari Firebase di
`android/app/google-services.json`. Plugin Google Services Android sudah
disiapkan di Gradle. Untuk menerima notifikasi dari server, konfigurasi FCM dan
service account Firebase juga harus diisi di menu Firebase Messaging pada
dashboard admin. Perangkat terdaftar setelah pengguna login dan FCM
menghasilkan token. Persetujuan izin/koreksi dan peringatan absensi dikirim
sesuai penerima workflow; hasil absensi rutin tetap ditampilkan langsung di
aplikasi agar tidak membanjiri notifikasi. Jangan memasukkan service account
ke aplikasi mobile.
