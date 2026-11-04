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
| **Consent ledger + face-processing gate** | 🆕 Inovasi lomba | Phase 4a — flag: `FEATURE_COMPETITION_CONSENT` |
| **AI Evaluation Dashboard + manual trials** | 🆕 Inovasi lomba | Phase 4b — flag: `FEATURE_AI_EVALUATION_DASHBOARD` |
| **Competition audit viewer** | 🆕 Inovasi lomba | Phase 4c — flag: `FEATURE_COMPETITION_AUDIT_VIEWER` |
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
- **Consent Phase 4a:** `competition_consents` adalah ledger append-only berisi NISN, status grant/withdraw, versi dan hash teks, aktor, dasar verifikasi, serta waktu. Teks default di `config/competition.php` adalah DRAF dengan placeholder kontak dan `text_approved=false`; grant nyata diblokir sampai pengguna meninjau teks, mengisi kontak, menaikkan versi, dan menyetujui konfigurasi. Untuk siswa di bawah umur, proses tertulis sekolah/wali tetap diperlukan. Ini bukan nasihat hukum atau klaim kepatuhan.
- **Trial Phase 4b:** `competition_evaluation_trials` hanya menyimpan jenis uji (`genuine`/`print`/`screen`), hasil, alasan, kode penguji anonim (`T1`, `T2`, ...), tanggal, catatan, dan fingerprint deduplikasi. Tidak ada kolom wajah, descriptor, NISN subjek, atau nama penguji. Import CSV memeriksa pola email, nomor panjang/telepon, dan kode penguji; teks bebas tetap harus diperiksa manusia untuk nama atau identitas lain sebelum ekspor/commit.
- **AI Evaluation:** dashboard hanya membaca challenge dan trial yang benar-benar tercatat. Review dipisahkan dari denominator; FAR/FRR tidak ditampilkan tanpa denominator. Snapshot di `docs/evaluation/` saat ini header-only dengan `n=0`; belum ada klaim angka performa atau hasil anti-spoofing.
- **Pencabutan consent:** middleware kompetisi memblokir pendaftaran, penyajian descriptor kepada browser, presensi wajah, dan ekstraksi ZIP bagi siswa tanpa consent aktif. Withdrawal tidak menghapus foto, descriptor, atau presensi dari tabel klien. Purge kompetisi tidak pernah menulis ke tabel klien.
- **Akses admin:** model `User` tidak memiliki kolom/konsep role. Route antrean, consent, AI Evaluation, dan audit viewer memakai guard admin aplikasi yang sudah ada (`auth:user`). Ini asumsi identitas admin bawaan, bukan sistem role/permission baru.
- **Penghapusan subjek sintetis:** command `competition:erase-synthetic-subject` hanya aktif dengan flag erasure dan consent gate, hanya menerima NISN berawalan `9999`, memerlukan pratinjau `--dry-run` dan `--confirm`, lalu menghapus catatan kompetisi terkait termasuk consent terbaru dan riwayat audit subjek sambil menyimpan event penghapusan baru. Tabel klien `siswa`/`presensi` tidak disentuh. Trial evaluasi tidak dihapus karena tidak memiliki NISN subjek.
- **Foto siswa:** Tidak ada foto nyata di instance lomba.
- **Face descriptor:** Dihitung di browser (client-side), tidak ada foto yang dikirim ke server saat matching.
- **Penyimpanan:** Face descriptor disimpan sebagai JSON array 128-float di kolom `siswa.face_descriptor`.
- **Tujuan:** Phase 2 shadow score dipakai untuk evaluasi tanpa mengubah hasil presensi. Phase 3a memakai nonce sekali pakai dan sinyal liveness turunan untuk keputusan berbasis aturan; skor menengah dan fallback liveness terbatas masuk antrean review.
- **Bobot skor:** Phase 2 shadow tetap memakai bobot komponen 40/40/20. Skor keputusan Phase 3a terpisah memakai bobot face/GPS/konsistensi/liveness 30/25/15/30 yang ditetapkan di `config/competition.php`; ambang accept/review dan minimum fallback juga berada di konfigurasi. Bobot Phase 2 tidak diubah.
- **Retensi dan tujuan:**

  | Data | Tujuan | Retensi default dan penghapusan |
  |------|--------|---------------------------------|
  | `competition_trust_scores` | Sinyal skor shadow untuk evaluasi | 90 hari (`COMPETITION_TRUST_SCORE_RETENTION_DAYS`); purge manual `competition:purge-trust-scores` |
  | `competition_liveness_challenges` | Validasi nonce, hasil challenge dan keputusan | 90 hari (`COMPETITION_LIVENESS_RETENTION_DAYS`); purge manual `competition:purge-liveness-data` |
  | `competition_verification_queue` | Tinjauan manusia atas verdict review | 90 hari; purge manual `competition:purge-liveness-data` |
  | `competition_audit_logs` | Jejak keputusan, akses admin, perubahan consent, impor, ekspor, dan purge | 90 hari (`COMPETITION_AUDIT_RETENTION_DAYS`); purge manual `competition:purge-liveness-data` |
  | `competition_consents` — status terbaru per NISN | Menegakkan grant/withdraw dan menghentikan pemrosesan wajah | Dipertahankan selama pemrosesan wajah masih mungkin; tidak ikut purge |
  | `competition_consents` — baris yang sudah digantikan | Riwayat versi/status persetujuan | 365 hari (`COMPETITION_CONSENT_HISTORY_RETENTION_DAYS`); purge manual hanya menghapus baris superseded |
  | `competition_evaluation_trials` | Bukti uji genuine/print/screen untuk evaluasi | 180 hari (`COMPETITION_EVALUATION_RETENTION_DAYS`); hanya purge manual, tidak dijadwalkan otomatis |
  | Tabel klien `siswa`/`presensi` (foto, descriptor, lokasi, presensi) | Alur operasional klien | Tidak dihapus oleh purge kompetisi; retensi mengikuti kebijakan/prosedur klien |

  `competition:purge-liveness-data --dry-run` menampilkan jumlah sebelum penghapusan; gunakan tanpa `--dry-run` hanya setelah jumlah ditinjau. Trust score memiliki command dry-run terpisah. Tidak ada purge yang dijadwalkan otomatis. Nilai dapat diubah lewat konfigurasi/environment; tabel di atas menjelaskan default.

  Penghapusan per subjek adalah operasi terpisah dan eksplisit; berbeda dari purge retensi, operasi ini menghapus juga status consent terbaru untuk subjek sintetis yang diminta. Jangan jalankan pada NISN nyata atau pada instance produksi klien.

## Seeding Instance Lomba

Jalankan hanya seeder sintetis yang khusus untuk lomba:

```powershell
php artisan db:seed --class=CompetitionSyntheticSeeder
```

Jangan jalankan `php artisan db:seed` tanpa class pada instance lomba: `DatabaseSeeder` memanggil `SiswaSeeder` lama dan membuat akun admin default. Atur `COMPETITION_DEMO_PASSWORD` melalui environment instance yang privat, atau biarkan kosong agar seeder membuat password acak dan mencetaknya sekali saat seeding. Kredensial juri harus disalurkan melalui form panitia, bukan disimpan di repository.

## Disclaimer AI

- Phase 2 shadow score dan Phase 3a challenge score menggunakan aturan dan ambang yang dapat dikonfigurasi, bukan model ML terlatih atau AI generatif.
- Phase 4 AI Evaluation adalah dashboard dan perhitungan metrik dari uji manual yang diimpor, bukan model AI atau klaim performa. Snapshot yang diperiksa saat ini memiliki `n=0`; belum ada FAR, FRR, atau tingkat serangan ditolak yang dapat dilaporkan.
- FAR/FRR menggunakan hanya keputusan accepted + rejected; hasil review ditampilkan terpisah dan tidak dihitung dalam denominator. Print dan screen dilaporkan terpisah. Hasil uji convenience sample kecil bukan jaminan keamanan.
- Teks consent default adalah DRAF, kontak placeholder, dan tidak dapat dipakai untuk grant sampai ditinjau pengguna. Sistem tidak menyatakan teks atau pemrosesan ini memenuhi ketentuan hukum.
- Sinyal liveness dihitung di browser; challenge nonce server menjamin keterikatan sesi, kedaluwarsa, sekali pakai, dan penolakan request tanpa nonce valid, tetapi tidak membuktikan bahwa sinyal browser tidak dipalsukan.
- Jalur `liveness_unavailable` dapat mencatat presensi sebagai menunggu review jika nilai minimum wajah/GPS lulus dan kuota harian belum habis; ini celah yang diketahui dan dicatat di README.
- Jarak wajah tetap dihitung di browser sampai Phase 3b; nilai `confidence` dapat dipalsukan oleh klien langsung.
- Uji foto cetak/layar adalah manual dan belum dianggap terverifikasi oleh laporan ini. Langkah uji disediakan di `docs/PHASE3A_MANUAL_TEST.md`.
- Pemeriksaan consent dan pengujian manual Phase 4 dijelaskan di `docs/PHASE4_MANUAL_TEST.md`. Belum ada hasil percobaan kamera manusia yang dimasukkan; jangan menyimpulkan foto cetak/layar ditolak hanya dari tes otomatis.
- Trust Score adalah **indikator bantu**, bukan keputusan final.
- Anomaly Detection adalah **sinyal statistik**, bukan vonis pelanggaran.
- Semua keputusan disiplin tetap di tangan manusia (guru/admin).
- Sistem tidak menggunakan data biometrik untuk tujuan selain verifikasi kehadiran.

## Arsitektur Feature Flag

Semua fitur lomba dikendalikan via `.env`:
```
FEATURE_TRUST_ENGINE_SHADOW=false    # Phase 2
FEATURE_TRUST_ENGINE_ENFORCE=false   # Phase 3
FEATURE_COMPETITION_CONSENT=false     # Phase 4a
FEATURE_AI_EVALUATION_DASHBOARD=false # Phase 4b
FEATURE_COMPETITION_AUDIT_VIEWER=false # Phase 4c
FEATURE_ANOMALY_DETECTION=false      # Phase 5
FEATURE_COMPETITION_MENU=false       # Master switch UI
```
Retention dapat dikonfigurasi dengan `COMPETITION_TRUST_SCORE_RETENTION_DAYS=90`, `COMPETITION_LIVENESS_RETENTION_DAYS=90`, `COMPETITION_AUDIT_RETENTION_DAYS=90`, `COMPETITION_CONSENT_HISTORY_RETENTION_DAYS=365`, dan `COMPETITION_EVALUATION_RETENTION_DAYS=180`. Dengan semua flag `false`, aplikasi berperilaku **identik** dengan versi pesanan klien.

## AI Coding Assistant

Pengembangan dibantu **GitHub Copilot (Copilot SDK in VS Code)**. Perubahan berbantuan AI ditinjau dan diuji sebelum commit. Trust Engine Phase 2 dan liveness challenge Phase 3a adalah pemrosesan berbasis aturan, bukan model AI terlatih atau generatif. Verifikasi jarak wajah server-side Phase 3b belum diimplementasikan.

## File Klien Lama yang Dimodifikasi

Dibandingkan branch klien `face-recognition`, file klien lama yang dimodifikasi adalah `.env.example` (flag dan nilai retensi contoh), `bootstrap/app.php` (route/middleware kondisional), `app/Providers/AppServiceProvider.php` (registrasi command kompetisi), dan `README.md` (dokumentasi lomba). Salinan README klien dipertahankan di `docs/README.client.md`. `config/competition.php` adalah file kompetisi baru dari Phase 1 yang diperluas untuk Phase 4, bukan file klien lama. Controller klien, `routes/web.php`, dan Blade klien tidak diubah.
