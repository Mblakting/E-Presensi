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
| Laravel | 11.x | MIT | Backend framework |
| TinyFaceDetector | — | MIT (bundled face-api) | Deteksi wajah real-time |
| FaceRecognitionNet | — | MIT (bundled face-api) | Ekstraksi 128-float descriptor |

## Data & Privasi

- **Data demo:** 100% sintetis. NISN prefix `9999` = jelas fiktif.
- **Foto siswa:** Tidak ada foto nyata di instance lomba.
- **Face descriptor:** Dihitung di browser (client-side), tidak ada foto yang dikirim ke server saat matching.
- **Penyimpanan:** Face descriptor disimpan sebagai JSON array 128-float di kolom `siswa.face_descriptor`.
- **Retensi:** Data demo dihapus setelah periode evaluasi lomba.

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
