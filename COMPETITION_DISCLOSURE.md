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
| **Liveness challenge + manual verification queue** | 🆕 Inovasi lomba | Flag: `FEATURE_TRUST_ENGINE_ENFORCE`; skor 40–70 ditahan sebelum masuk tabel presensi sampai disetujui admin |
| **Consent ledger + face-processing gate** | 🆕 Inovasi lomba | Phase 4a — flag: `FEATURE_COMPETITION_CONSENT` |
| **AI Evaluation Dashboard + manual trials** | 🆕 Inovasi lomba | Phase 4b — flag: `FEATURE_AI_EVALUATION_DASHBOARD` |
| **Competition audit viewer** | 🆕 Inovasi lomba | Phase 4c — flag: `FEATURE_COMPETITION_AUDIT_VIEWER` |
| **Competition-only synthetic data erasure** | 🆕 Inovasi lomba | Phase 4c — flag: `FEATURE_COMPETITION_DATA_ERASURE` dan consent gate |

## Framework & Library AI

| Library | Versi | Lisensi | Peran |
|---------|-------|---------|-------|
| face-api.js | 0.22.2 | MIT | Face detection & descriptor extraction (client-side) |
| Laravel | 12.x | MIT | Backend framework |
| TinyFaceDetector | — | MIT (bundled face-api) | Deteksi wajah real-time |
| FaceRecognitionNet | — | MIT (bundled face-api) | Ekstraksi 128-float descriptor |

## Data & Privasi

- **Data demo:** data siswa pada `CompetitionSyntheticSeeder` sintetis. NISN prefix `9999` digunakan untuk data demo tersebut.
- **Data pribadi:** NISN tetap merupakan data pribadi walaupun NISN demo bersifat sintetis. Trust score shadow maupun skor keputusan antrean mengaitkan NISN dengan skor/verdict.
- **Sinyal lokasi/waktu:** validasi presensi lama tetap menyimpan lokasi sesuai perilaku klien. Trust Engine tidak menyimpan koordinat mentah pada `raw_signals`; ia hanya menyimpan jarak hasil perhitungan dari lokasi sekolah, akurasi GPS, dan selisih waktu hasil perhitungan. Nilai-nilai turunan tersebut tetap dapat menjadi data pribadi ketika dikaitkan dengan NISN.
- **Challenge/review:** `competition_liveness_challenges` menyimpan NISN, hash nonce, hash sesi, instruksi challenge, status/waktu, dan skor turunan. `competition_manual_verifications` menyimpan skor/verdict, alasan, waktu/jenis presensi, koordinat mentah dari `lokasi`, browser-supplied `confidence`, identitas admin/keputusan, dan path ke snapshot JPEG. Snapshot JPEG disimpan privat untuk review admin; tabel kompetisi tidak menyimpan descriptor wajah atau nonce plaintext. Jika disetujui, snapshot disalin ke penyimpanan foto presensi klien dan baris `presensi` ditulis. Jika ditolak, tabel klien tidak berubah. Audit event keputusan menggunakan tabel audit kompetisi yang juga menyimpan actor/IP/user-agent sesuai skema audit yang sudah ada.
- **Consent Phase 4a:** `competition_consents` adalah ledger append-only berisi NISN, status grant/withdraw, versi dan hash teks, aktor, dasar verifikasi, serta waktu. Teks default di `config/competition.php` adalah DRAF dengan placeholder kontak dan `text_approved=false`; grant nyata diblokir sampai pengguna meninjau teks, mengisi kontak, menaikkan versi, dan menyetujui konfigurasi. Untuk siswa di bawah umur, proses tertulis sekolah/wali tetap diperlukan. Ini bukan nasihat hukum atau klaim kepatuhan.
- **Trial Phase 4b:** `competition_evaluation_trials` hanya menyimpan jenis uji (`genuine`/`print`/`screen`), hasil, alasan, kode penguji anonim (`T1`, `T2`, ...), tanggal, catatan, dan fingerprint deduplikasi. Tidak ada kolom wajah, descriptor, NISN subjek, atau nama penguji. Import CSV memeriksa pola email, nomor panjang/telepon, dan kode penguji; teks bebas tetap harus diperiksa manusia untuk nama atau identitas lain sebelum ekspor/commit.
- **AI Evaluation:** dashboard hanya membaca challenge dan trial yang benar-benar tercatat. Review dipisahkan dari denominator; FAR/FRR tidak ditampilkan tanpa denominator. Snapshot di `docs/evaluation/` saat ini header-only dengan `n=0`; belum ada klaim angka performa atau hasil anti-spoofing.
- **Pencabutan consent:** middleware kompetisi memblokir pendaftaran, penyajian descriptor kepada browser, presensi wajah, dan ekstraksi ZIP bagi siswa tanpa consent aktif. Withdrawal tidak menghapus foto, descriptor, atau presensi dari tabel klien. Purge kompetisi tidak pernah menulis ke tabel klien.
- **Akses admin:** model `User` tidak memiliki kolom/konsep role. Route antrean, consent, AI Evaluation, dan audit viewer memakai guard admin aplikasi yang sudah ada (`auth:user`). Ini asumsi identitas admin bawaan, bukan sistem role/permission baru.
- **Penghapusan subjek sintetis:** command `competition:erase-synthetic-subject` hanya aktif dengan flag erasure dan consent gate, hanya menerima NISN berawalan `9999`, memerlukan pratinjau `--dry-run` dan `--confirm`, lalu menghapus catatan kompetisi terkait termasuk consent terbaru dan riwayat audit subjek sambil menyimpan event penghapusan baru. Tabel klien `siswa`/`presensi` tidak disentuh. Trial evaluasi tidak dihapus karena tidak memiliki NISN subjek.
- **Foto siswa:** Tidak ada foto nyata di instance lomba.
- **Face descriptor — alur klien pre-existing:** Descriptor dihitung di browser (client-side), tidak ada foto yang dikirim ke server saat matching. Alur face recognition klien yang sudah ada menyimpan descriptor sebagai JSON array 128-float di kolom `siswa.face_descriptor`.
- **Face descriptor — tabel kompetisi:** Tabel antrean, challenge, skor, dan audit kompetisi tidak menyimpan descriptor wajah. Antrean human review Phase 3b bukan verifikasi descriptor di server.
- **Tujuan:** Phase 2 shadow score dipakai untuk evaluasi tanpa mengubah hasil presensi. Phase 3a memakai nonce sekali pakai dan sinyal liveness turunan untuk keputusan berbasis aturan; skor menengah dan fallback liveness terbatas masuk antrean review.
- **Bobot skor:** Phase 2 shadow tetap memakai bobot komponen 40/40/20. Skor keputusan Phase 3a terpisah memakai bobot face/GPS/konsistensi/liveness 30/25/15/30 yang ditetapkan di `config/competition.php`; ambang accept/review dan minimum fallback juga berada di konfigurasi. Bobot Phase 2 tidak diubah.
- **Retensi dan tujuan:**

  | Data | Tujuan | Retensi default dan penghapusan |
  |------|--------|---------------------------------|
  | `competition_trust_scores` | Skor shadow serta skor keputusan review yang ditandai `is_shadow=false` | 90 hari (`COMPETITION_TRUST_SCORE_RETENTION_DAYS`); purge manual `competition:purge-trust-scores` |
  | `competition_liveness_challenges` | Validasi nonce, hasil challenge dan keputusan | 90 hari (`COMPETITION_LIVENESS_RETENTION_DAYS`); purge manual `competition:purge-liveness-data` |
  | `competition_verification_queue` | Tinjauan manusia atas verdict review | 90 hari; purge manual `competition:purge-liveness-data` |
  | `competition_manual_verifications` + snapshot privat | Menahan presensi grey-area sampai keputusan admin; menyimpan koordinat mentah, `confidence` dari browser, serta bukti visual review | 90 hari (`COMPETITION_MANUAL_VERIFICATION_RETENTION_DAYS`) sejak `attempted_at`; purge manual `competition:purge-liveness-data` mencakup semua status (pending, approved, rejected) dan file snapshot privat |
  | `competition_audit_logs` | Jejak keputusan, akses admin, perubahan consent, impor, ekspor, dan purge | 90 hari (`COMPETITION_AUDIT_RETENTION_DAYS`); purge manual `competition:purge-liveness-data` |
  | `competition_consents` — status terbaru per NISN | Menegakkan grant/withdraw dan menghentikan pemrosesan wajah | Dipertahankan selama pemrosesan wajah masih mungkin; tidak ikut purge |
  | `competition_consents` — baris yang sudah digantikan | Riwayat versi/status persetujuan | 365 hari (`COMPETITION_CONSENT_HISTORY_RETENTION_DAYS`); purge manual hanya menghapus baris superseded |
  | `competition_evaluation_trials` | Bukti uji genuine/print/screen untuk evaluasi | 180 hari (`COMPETITION_EVALUATION_RETENTION_DAYS`); hanya purge manual, tidak dijadwalkan otomatis |
  | Tabel klien `siswa`/`presensi` (foto, descriptor, lokasi, presensi) | Alur operasional klien | Tidak dihapus oleh purge kompetisi; retensi mengikuti kebijakan/prosedur klien |

  `competition:purge-liveness-data --dry-run` menampilkan jumlah sebelum penghapusan; gunakan tanpa `--dry-run` hanya setelah jumlah ditinjau. Trust score memiliki command dry-run terpisah. Snapshot yang sudah disetujui dan disalin ke penyimpanan foto presensi klien tidak dihapus oleh purge kompetisi. Tidak ada purge yang dijadwalkan otomatis. Nilai dapat diubah lewat konfigurasi/environment; tabel di atas menjelaskan default.

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
- Jalur `liveness_unavailable` dapat memasukkan percobaan ke antrean privat jika nilai minimum wajah/GPS lulus dan kuota harian belum habis; tabel presensi klien hanya ditulis setelah admin menyetujui.
- Jarak wajah/`confidence` tetap dihitung dan dikirim browser, sehingga dapat dipalsukan oleh klien langsung; verifikasi jarak descriptor di server belum tersedia. Antrean review manual Phase 3b adalah fitur human-in-the-loop yang terpisah, bukan verifikasi server-side.
- Belum ada hasil uji kamera manusia. Snapshot evaluasi saat ini `n=0`; tidak ada hasil foto cetak/layar atau metrik FAR/FRR yang diklaim.
- Uji foto cetak/layar adalah manual dan belum dianggap terverifikasi oleh laporan ini. Langkah uji disediakan di `docs/PHASE3A_MANUAL_TEST.md`.
- Pemeriksaan consent dan pengujian manual Phase 4 dijelaskan di `docs/PHASE4_MANUAL_TEST.md`. Belum ada hasil percobaan kamera manusia yang dimasukkan; jangan menyimpulkan foto cetak/layar ditolak hanya dari tes otomatis.
- Trust Score adalah **indikator bantu**, bukan keputusan final.
- Semua keputusan disiplin tetap di tangan manusia (guru/admin).
- Sistem tidak menggunakan data biometrik untuk tujuan selain verifikasi kehadiran.

## Arsitektur Feature Flag

Semua feature flag yang benar-benar tersedia dibaca oleh `config/competition.php` dan default ke `false`:

```
FEATURE_TRUST_ENGINE_SHADOW=false
FEATURE_TRUST_ENGINE_ENFORCE=false
FEATURE_COMPETITION_CONSENT=false
FEATURE_AI_EVALUATION_DASHBOARD=false
FEATURE_COMPETITION_AUDIT_VIEWER=false
FEATURE_COMPETITION_DATA_ERASURE=false
```

Parameter lain yang dibaca konfigurasi adalah `COMPETITION_DEMO_PASSWORD`, `COMPETITION_CHALLENGE_TTL_SECONDS`, `COMPETITION_TRUST_SCORE_RETENTION_DAYS`, `COMPETITION_LIVENESS_RETENTION_DAYS`, `COMPETITION_AUDIT_RETENTION_DAYS`, `COMPETITION_CONSENT_HISTORY_RETENTION_DAYS`, dan `COMPETITION_EVALUATION_RETENTION_DAYS`. Nilai contoh dan default-nya tercantum di `.env.example` serta tabel retensi di atas. Dengan semua feature flag `false`, aplikasi berperilaku **identik** dengan versi pesanan klien.

## Potensi Pengembangan — Belum Diimplementasikan

- Deteksi anomali kehadiran belum tersedia. Belum ada analitik anomali, model, route, atau flag aktif; ide ini tidak termasuk fitur yang dapat digunakan atau dinilai pada versi ini.
- Menu navigasi kompetisi terpisah belum tersedia; halaman kompetisi dibuka melalui route yang terdaftar ketika flag fiturnya aktif. Tidak ada master flag menu.

## AI Coding Assistant

Pengembangan dibantu **GitHub Copilot (Copilot SDK in VS Code)**. Perubahan berbantuan AI ditinjau dan diuji sebelum commit. Trust Engine Phase 2 dan liveness challenge Phase 3a adalah pemrosesan berbasis aturan, bukan model AI terlatih atau generatif. Verifikasi jarak descriptor wajah di server belum diimplementasikan; antrean review manual Phase 3b adalah fitur human-in-the-loop yang terpisah.

## File Klien Lama yang Dimodifikasi

Dibandingkan branch klien `face-recognition`, file klien lama yang dimodifikasi adalah `.env.example` (flag dan nilai retensi contoh), `bootstrap/app.php` (route/middleware kondisional), `app/Providers/AppServiceProvider.php` (registrasi command kompetisi), dan `README.md` (dokumentasi lomba). Salinan README klien dipertahankan di `docs/README.client.md`. `config/competition.php` adalah file kompetisi baru dari Phase 1 yang diperluas untuk Phase 4, bukan file klien lama. Controller klien, `routes/web.php`, dan Blade klien tidak diubah.
