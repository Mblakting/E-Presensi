# Panduan Juri

Panduan ini untuk instance kompetisi yang terpisah dari production klien. Gunakan hanya akun demo yang disediakan panitia dan data sintetis dengan NISN berawalan `9999`. Jangan masukkan data siswa nyata atau kredensial ke repository.

## Konfigurasi instance demo

Atur environment secara privat pada deployment, kemudian jalankan migrasi kompetisi pada database demo. Jangan menjalankan `php artisan db:seed` tanpa nama class; `DatabaseSeeder` juga menjalankan seeder klien. Jika data siswa sintetis perlu dibuat, jalankan hanya seeder kompetisi:

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

Masuklah dengan kredensial demo admin/siswa yang diberikan panitia melalui kanal privat. Jangan menggunakan akun admin bawaan atau mempublikasikan password.

## Urutan demo

1. Buka `/` untuk masuk sebagai siswa sintetis, atau `/panel` untuk masuk sebagai admin/juri. Kredensial harus berasal dari panitia.
2. Buka `/competition/guide` untuk melihat pemisahan fitur pre-existing dan fitur lomba, cara mencoba, serta batasannya. Halaman panduan bersifat publik dan tidak menampilkan data siswa.
3. Setelah login, buka `/competition`. Admin melihat status flag dan ringkasan agregat modul; siswa melihat status persetujuannya dan tautan ke `/presensi/face`. Flag navigasi harus aktif.
4. Jika consent telah ditinjau dan diaktifkan secara sah, uji alur persetujuan sesuai prosedur dan buka presensi wajah siswa sintetis. Jangan merekam persetujuan nyata ketika teks masih draf.
5. Untuk menunjukkan Trust Engine, gunakan presensi terkontrol. Dengan `FEATURE_TRUST_ENGINE_SHADOW=true`, skor turunan dicatat tanpa mengubah keputusan presensi. Antrean di `/competition/reviews` hanya tersedia dengan enforce aktif; percobaan grey-area menunggu keputusan manusia, dan tabel presensi klien baru ditulis setelah admin menyetujui.
6. Buka `/competition/ai-evaluation` untuk melihat challenge dan uji manual yang benar-benar tercatat. Tampilan `n=0` berarti belum ada trial yang diimpor; jangan mengklaim metrik FAR/FRR atau keberhasilan anti-spoofing tanpa bukti yang tercatat.
7. Buka `/competition/audit` untuk melihat event audit jika audit viewer aktif. Setelah demo, kembalikan flag enforce ke `false` bila tidak lagi diperlukan.

## Batasan yang harus disampaikan

Trust Engine dan pemeriksaan liveness adalah aturan dan sinyal turunan dari browser, bukan model AI terlatih. Jarak wajah/`confidence` tetap dihitung dan dikirim browser; verifikasi jarak descriptor di server belum tersedia dan antrean Phase 3b adalah human-in-the-loop review, bukan verifikasi server-side. Data review manual dapat mencakup koordinat mentah, `confidence`, dan snapshot JPEG privat sesuai kebijakan retensi. Liveness belum membuktikan ketahanan terhadap foto cetak, layar, deepfake, atau injeksi kamera. Lihat [COMPETITION_DISCLOSURE.md](../COMPETITION_DISCLOSURE.md) untuk pengungkapan teknologi dan data yang lebih lengkap.
