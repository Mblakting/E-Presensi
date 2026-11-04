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
| **Trust Engine (Enforce)** | 🆕 Inovasi lomba | Phase 3 — flag: `FEATURE_TRUST_ENGINE_ENFORCE` |
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
- **Foto siswa:** Tidak ada foto nyata di instance lomba.
- **Face descriptor:** Dihitung di browser (client-side), tidak ada foto yang dikirim ke server saat matching.
- **Penyimpanan:** Face descriptor disimpan sebagai JSON array 128-float di kolom `siswa.face_descriptor`.
- **Tujuan:** trust score dan sinyal turunannya dipakai untuk evaluasi integritas presensi secara shadow; skor tidak mengubah hasil presensi pada Phase 2.
- **Retensi:** trust score lebih lama dari 90 hari dapat dihapus dengan `php artisan competition:purge-trust-scores --days=90`. Tinjau dahulu memakai `--dry-run`; jalankan purge berkala pada instance lomba dan hapus seluruh data demo setelah evaluasi.

## Seeding Instance Lomba

Jalankan hanya seeder sintetis yang khusus untuk lomba:

```powershell
php artisan db:seed --class=CompetitionSyntheticSeeder
```

Jangan jalankan `php artisan db:seed` tanpa class pada instance lomba: `DatabaseSeeder` memanggil `SiswaSeeder` lama dan membuat akun admin default. Atur `COMPETITION_DEMO_PASSWORD` melalui environment instance yang privat, atau biarkan kosong agar seeder membuat password acak dan mencetaknya sekali saat seeding. Kredensial juri harus disalurkan melalui form panitia, bukan disimpan di repository.

## Disclaimer AI

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

Pengembangan dibantu **GitHub Copilot (Copilot SDK in VS Code)**. Perubahan berbantuan AI ditinjau dan diuji sebelum commit. Trust Engine Phase 2 adalah scoring berbasis aturan, bukan model AI terlatih atau generatif. Challenge-response liveness Phase 3 belum diimplementasikan.
