# AI & Technology Disclosure — Digifest 2026

## Pemisahan Pre-Existing vs Fitur Lomba

| Fitur | Status | Keterangan |
|-------|--------|-----------|
| Absensi GPS + Foto | ✅ Pre-existing (klien) | Tidak dimodifikasi |
| Face Recognition Absensi | ✅ Pre-existing (klien) | face-api.js v0.22.2, client-side |
| Geofencing Haversine 100m | ✅ Pre-existing (klien) | Formula Haversine, server-side |
| Manajemen Siswa/Jurusan | ✅ Pre-existing (klien) | Tidak dimodifikasi |
| Import CSV Siswa | ✅ Pre-existing (klien) | Tidak dimodifikasi |
| Batch Face ID Generator | ✅ Pre-existing (klien) | Tidak dimodifikasi |
| **Trust Engine (Shadow)** | 🆕 Inovasi lomba | Phase 2 — flag: `FEATURE_TRUST_ENGINE_SHADOW` |
| **Liveness challenge + review queue** | 🆕 Inovasi lomba | Phase 3a — flag: `FEATURE_TRUST_ENGINE_ENFORCE` |
| **AI Evaluation Dashboard** | 🆕 Inovasi lomba | Phase 4 — flag: `FEATURE_AI_EVALUATION_DASHBOARD` |
| **Anomaly Detection** | 🆕 Inovasi lomba | Phase 5 — flag: `FEATURE_ANOMALY_DETECTION` |

## Framework & Library AI

| Library | Versi | Lisensi | Peran |
|---------|-------|---------|-------|
| face-api.js | 0.22.2 | MIT | Face detection & descriptor extraction (client-side) |
| Laravel | 12.x | MIT | Backend framework |
| TinyFaceDetector | — | MIT (bundled face-api) | Deteksi wajah real-time |
| FaceRecognitionNet | — | MIT (bundled face-api) | Ekstraksi 128-float descriptor |

## Data & Privasi

- **Data demo:** data siswa pada `CompetitionSyntheticSeeder` sintetis. NISN prefix `9999` digunakan untuk data demo tersebut.
- **Data pribadi:** NISN tetap merupakan data pribadi walaupun NISN demo bersifat sintetis. Trust score mengaitkan NISN dengan skor/verdict.
- **Sinyal lokasi/waktu:** validasi presensi lama tetap menyimpan lokasi sesuai perilaku klien. Trust Engine tidak menyimpan koordinat mentah pada `raw_signals`; ia hanya menyimpan jarak hasil perhitungan dari lokasi sekolah, akurasi GPS, dan selisih waktu hasil perhitungan. Nilai-nilai turunan tersebut tetap dapat menjadi data pribadi ketika dikaitkan dengan NISN.
- **Challenge/review Phase 3a:** tabel baru menyimpan NISN, hash nonce, hash sesi, instruksi challenge, status/waktu, skor turunan, alasan, dan keputusan reviewer. Tidak menyimpan nonce plaintext, foto, descriptor wajah, koordinat, atau sampel frame. Audit event keputusan menggunakan tabel audit kompetisi yang juga menyimpan actor/IP/user-agent sesuai skema audit yang sudah ada.
- **Foto siswa:** Tidak ada foto nyata di instance lomba.
- **Face descriptor:** Dihitung di browser (client-side), tidak ada foto yang dikirim ke server saat matching.
- **Penyimpanan:** Face descriptor disimpan sebagai JSON array 128-float di kolom `siswa.face_descriptor`.
- **Tujuan:** Phase 2 shadow score dipakai untuk evaluasi tanpa mengubah hasil presensi. Phase 3a memakai nonce sekali pakai dan sinyal liveness turunan untuk keputusan berbasis aturan; skor menengah dan fallback liveness terbatas masuk antrean review.
- **Bobot skor:** Phase 2 shadow tetap memakai bobot komponen 40/40/20. Skor keputusan Phase 3a terpisah memakai bobot face/GPS/konsistensi/liveness 30/25/15/30 yang ditetapkan di `config/competition.php`; ambang accept/review dan minimum fallback juga berada di konfigurasi. Bobot Phase 2 tidak diubah.
- **Retensi:** trust score lebih lama dari 90 hari dapat dipratinjau dengan `php artisan competition:purge-trust-scores --days=90 --dry-run` lalu dihapus dengan command tanpa `--dry-run`. Challenge, queue, dan audit event review lama dapat dipratinjau/dihapus dengan `php artisan competition:purge-liveness-data --days=90 --dry-run` lalu dijalankan tanpa `--dry-run`. Jalankan purge berkala pada instance lomba dan hapus seluruh data demo setelah evaluasi.

## Seeding Instance Lomba

Jalankan hanya seeder sintetis yang khusus untuk lomba:

```powershell
php artisan db:seed --class=CompetitionSyntheticSeeder
```

Jangan jalankan `php artisan db:seed` tanpa class pada instance lomba: `DatabaseSeeder` memanggil `SiswaSeeder` lama dan membuat akun admin default. Atur `COMPETITION_DEMO_PASSWORD` melalui environment instance yang privat, atau biarkan kosong agar seeder membuat password acak dan mencetaknya sekali saat seeding. Kredensial juri harus disalurkan melalui form panitia, bukan disimpan di repository.

## Disclaimer AI

- Phase 2 shadow score dan Phase 3a challenge score menggunakan aturan dan ambang yang dapat dikonfigurasi, bukan model ML terlatih atau AI generatif.
- Sinyal liveness dihitung di browser; challenge nonce server menjamin keterikatan sesi, kedaluwarsa, sekali pakai, dan penolakan request tanpa nonce valid, tetapi tidak membuktikan bahwa sinyal browser tidak dipalsukan.
- Jalur `liveness_unavailable` dapat mencatat presensi sebagai menunggu review jika nilai minimum wajah/GPS lulus dan kuota harian belum habis; ini celah yang diketahui dan dicatat di README.
- Jarak wajah tetap dihitung di browser sampai Phase 3b; nilai `confidence` dapat dipalsukan oleh klien langsung.
- Uji foto cetak/layar adalah manual dan belum dianggap terverifikasi oleh laporan ini. Langkah uji disediakan di `docs/PHASE3A_MANUAL_TEST.md`.
- Trust Score adalah **indikator bantu**, bukan keputusan final.
- Anomaly Detection adalah **sinyal statistik**, bukan vonis pelanggaran.
- Semua keputusan disiplin tetap di tangan manusia (guru/admin).
- Sistem tidak menggunakan data biometrik untuk tujuan selain verifikasi kehadiran.

## Arsitektur Feature Flag

Semua fitur lomba dikendalikan via `.env`:
```
FEATURE_TRUST_ENGINE_SHADOW=false    # Phase 2
FEATURE_TRUST_ENGINE_ENFORCE=false   # Phase 3
FEATURE_AI_EVALUATION_DASHBOARD=false # Phase 4
FEATURE_ANOMALY_DETECTION=false      # Phase 5
FEATURE_COMPETITION_MENU=false       # Master switch UI
```
Dengan semua flag `false`, aplikasi berperilaku **identik** dengan versi pesanan klien.

## AI Coding Assistant

Pengembangan dibantu **GitHub Copilot (Copilot SDK in VS Code)**. Perubahan berbantuan AI ditinjau dan diuji sebelum commit. Trust Engine Phase 2 dan liveness challenge Phase 3a adalah pemrosesan berbasis aturan, bukan model AI terlatih atau generatif. Verifikasi jarak wajah server-side Phase 3b belum diimplementasikan.

## File Klien Lama yang Dimodifikasi

Perubahan lama yang menyambungkan fitur kompetisi tetap dibatasi dan flag-gated: `.env.example` (nama flag/TTL), `bootstrap/app.php` (route/middleware kondisional), `config/competition.php` (flag, bobot, dan ambang), dan `app/Providers/AppServiceProvider.php` (registrasi command). `README.md` dan dokumen disclosure diperbarui untuk dokumentasi. Controller, `routes/web.php`, dan view presensi klien tidak diubah.
