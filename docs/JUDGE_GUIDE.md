# Panduan Juri

Panduan ini untuk instance kompetisi yang terpisah dari production klien. Gunakan hanya akun demo yang disediakan panitia dan data sintetis dengan NISN berawalan `9999`. Jangan masukkan data siswa nyata atau kredensial ke repository.

## Konfigurasi instance demo

Atur environment secara privat pada instance Railway kompetisi yang terisolasi, kemudian jalankan migrasi pada database demo:

```powershell
php artisan migrate --force
```

Jangan menjalankan `php artisan db:seed` tanpa nama class; `DatabaseSeeder` juga menjalankan seeder klien. Jika data siswa sintetis perlu dibuat, jalankan hanya seeder kompetisi:

```powershell
php artisan db:seed --class=CompetitionSyntheticSeeder
```

Flag berikut diperlukan untuk navigasi, laporan, dan audit:

```dotenv
FEATURE_COMPETITION_NAV=true
FEATURE_AI_EVALUATION_DASHBOARD=true
FEATURE_COMPETITION_AUDIT_VIEWER=true
```

`FEATURE_TRUST_ENGINE_SHADOW=true` bersifat opsional untuk mencatat skor bayangan setelah presensi berhasil tanpa mengubah hasil presensi. Antrean review memerlukan `FEATURE_TRUST_ENGINE_ENFORCE=true`; nyalakan hanya untuk sesi uji terkontrol karena percobaan dapat ditolak atau ditahan sebelum masuk ke tabel presensi klien. Siapkan cara mematikan flag sebagai cadangan demo. Consent memerlukan `FEATURE_COMPETITION_CONSENT=true`, tetapi teks default masih draf dan `text_approved=false`; jangan ubah status persetujuan teks sebelum peninjauan dan persetujuan yang semestinya. Semua flag lain default `false`.

`COMPETITION_CONSENT_TEXT_APPROVED=false` dan `COMPETITION_CONSENT_CONTACT=` adalah nilai aman awal. Jangan mengubah status persetujuan teks atas nama pengguna; teks harus ditinjau sebelum diaktifkan. Jika gate aktif saat teks masih draf, presensi wajah diblokir dan siswa dapat memakai presensi non-wajah.

Untuk membuat atau memperbarui satu admin juri, isi `COMPETITION_JUDGE_EMAIL` dan `COMPETITION_JUDGE_PASSWORD` secara privat di Variables Railway, lalu jalankan:

```powershell
php artisan competition:create-demo-admin
```

Password minimal 12 karakter, tidak dicetak dan tidak ditulis ke log oleh command. Command menolak berjalan jika ada NISN yang tidak berawalan `9999`; opsi `--allow-real-data` hanya untuk kondisi khusus yang disetujui secara eksplisit dan menampilkan peringatan bahwa juri dapat melihat data siswa nyata. Jangan gunakan opsi itu untuk demo publik. Password diberikan kepada juri melalui form panitia, bukan repository. Data siswa demo dapat dibuat melalui seeder di atas; jangan jalankan seeder klien atau `DatabaseSeeder`.

## Urutan demo

1. Pastikan migrasi dan seeder sintetis selesai; masuk sebagai admin melalui `/panel` dengan akun juri yang dibuat dari environment privat. Password diberikan panitia melalui form, bukan repo.
2. Buka `/competition/guide` untuk melihat fitur pre-existing, fitur baru, dan batasan. Panduan publik tidak memuat data siswa.
3. Setelah masuk, buka `/competition` untuk melihat status modul dan alur demo. Gunakan hanya akun siswa sintetis untuk membuka `/presensi/face`.
4. Uji presensi wajah hanya jika kamera dan proses consent telah disiapkan. Saat gate aktif dan `COMPETITION_CONSENT_TEXT_APPROVED=false`, presensi wajah sengaja diblokir; gunakan `/presensi/create` sebagai jalur non-wajah.
5. Untuk memperlihatkan human-in-the-loop, aktifkan enforce hanya pada uji terkontrol, lalu tinjau `/competition/reviews`. Presensi grey-area/fallback belum ditulis ke tabel klien sampai admin menyetujui.
6. Buka `/competition/ai-evaluation` untuk melihat challenge dan uji manual yang benar-benar tercatat. `n=0` berarti belum ada uji manual; jangan menyatakan angka FAR/FRR atau hasil anti-spoofing.
7. Buka `/competition/audit` dan `/competition/consents` untuk memeriksa catatan audit serta status persetujuan. Setelah demo, matikan `FEATURE_TRUST_ENGINE_ENFORCE` jika tidak diperlukan.

## Batasan yang harus disampaikan

Trust Engine dan pemeriksaan liveness adalah aturan dan sinyal turunan dari browser, bukan model AI terlatih. Jarak wajah/`confidence` tetap dihitung dan dikirim browser; verifikasi jarak descriptor di server belum tersedia dan antrean Phase 3b adalah human-in-the-loop review, bukan verifikasi server-side. Data review manual dapat mencakup koordinat mentah, `confidence`, dan snapshot JPEG privat sesuai kebijakan retensi. Liveness belum membuktikan ketahanan terhadap foto cetak, layar, deepfake, atau injeksi kamera. Lihat [COMPETITION_DISCLOSURE.md](../COMPETITION_DISCLOSURE.md) untuk pengungkapan teknologi dan data yang lebih lengkap.
