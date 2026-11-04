<?php

/**
 * Digifest 2026 Competition Feature Flags
 *
 * SEMUA flag default FALSE.
 * Jika semua false → perilaku aplikasi identik 100% dengan versi klien.
 * Aktifkan HANYA di instance Railway lomba via environment variable.
 * JANGAN set true di .env production klien.
 */
return [
    'demo_password' => env('COMPETITION_DEMO_PASSWORD'),
    'competition_nav' => env('FEATURE_COMPETITION_NAV', false),

    /*
     * Phase 2: Trust Engine berjalan diam-diam (shadow mode).
     * Menghitung trust score tiap absen tanpa mengubah hasil absen asli.
     */
    'trust_engine_shadow' => env('FEATURE_TRUST_ENGINE_SHADOW', false),

    /*
     * Phase 3a: Trust Engine enforce mode.
     * Challenge-response, keputusan skor, dan antrean review.
     */
    'trust_engine_enforce' => env('FEATURE_TRUST_ENGINE_ENFORCE', false),
    'liveness_challenge' => [
        'ttl_seconds' => env('COMPETITION_CHALLENGE_TTL_SECONDS', 90),
        'weights' => [
            'face' => 30,
            'gps' => 25,
            'consistency' => 15,
            'liveness' => 30,
        ],
        'face_perfect_distance' => 0.20,
        'face_distance_limit' => 0.55,
        'thresholds' => [
            'accept' => 71,
            'review' => 40,
            'face_min' => 18,
            'gps_min' => 15,
            'unavailable_daily_limit' => 2,
            'blink_delta_min' => 0.10,
            'turn_delta_min' => 0.12,
            'proof_min_elapsed_ms' => 800,
            'proof_max_elapsed_ms' => 15000,
        ],
    ],

    /*
     * Phase 4a: Consent gate for face enrollment and attendance.
     * Keep text_approved false until the draft has been reviewed.
     */
    'consent_gate' => env('FEATURE_COMPETITION_CONSENT', false),
    'consent' => [
        'version' => 'draft-2',
        'text_approved' => false,
        'text' => <<<'CONSENT'
DRAF PERSETUJUAN PEMROSESAN WAJAH — versi draft-2

Tujuan: fitur ini menggunakan pengenalan wajah sebagai pilihan untuk pendaftaran wajah dan presensi pada instance demonstrasi E-Presensi.

Data yang diproses: representasi numerik (descriptor) wajah dan foto snapshot saat pendaftaran, foto saat presensi wajah, serta data presensi dan lokasi yang diperlukan oleh alur aplikasi. Jika percobaan presensi ditahan untuk pemeriksaan admin, snapshot JPEG dan lokasi percobaan disimpan pada tabel antrean kompetisi dan snapshot disimpan privat untuk admin. Data biometrik lama disimpan pada tabel siswa milik aplikasi; catatan kompetisi disimpan terpisah.

Retensi: challenge, skor, audit, antrean verifikasi, dan snapshot privat direncanakan disimpan paling lama 90 hari melalui purge manual. Jika admin menyetujui presensi, foto disalin ke penyimpanan foto presensi milik aplikasi klien dan mengikuti prosedur retensi/penghapusan klien; foto tersebut tidak dihapus oleh purge kompetisi. Catatan persetujuan terbaru dipertahankan selama pemrosesan wajah masih mungkin; riwayat yang telah digantikan direncanakan disimpan 365 hari. Descriptor, foto, dan presensi pada tabel aplikasi klien tidak dihapus otomatis oleh penarikan persetujuan.

Penarikan persetujuan: admin dapat mencatat penarikan. Setelah tercatat, aplikasi kompetisi akan menghentikan pendaftaran dan pemrosesan presensi wajah untuk NISN tersebut. Untuk meminta penghapusan descriptor atau foto yang sudah tersimpan pada tabel klien, hubungi pengelola agar mengikuti prosedur penghapusan data klien yang berwenang. Presensi non-wajah tetap dapat digunakan sesuai alur yang tersedia.

Kontak pengelola: [isi kontak pengelola sebelum teks ini disetujui].

Teks ini adalah DRAF untuk ditinjau pengguna dan bukan pernyataan bahwa persyaratan hukum telah dipenuhi. Untuk siswa di bawah umur, persetujuan harus mengikuti proses tertulis sekolah atau wali yang berwenang. Jangan gunakan untuk data nyata sebelum teks dan prosesnya ditinjau serta disetujui.
CONSENT,
    ],
    'retention' => [
        'trust_scores_days' => env('COMPETITION_TRUST_SCORE_RETENTION_DAYS', 90),
        'liveness_days' => env('COMPETITION_LIVENESS_RETENTION_DAYS', 90),
        'audit_days' => env('COMPETITION_AUDIT_RETENTION_DAYS', 90),
        'consent_history_days' => env('COMPETITION_CONSENT_HISTORY_RETENTION_DAYS', 365),
    ],

    /*
     * Phase 4: Halaman AI Evaluation Dashboard (metrik FAR/FRR, audit log).
     */
    'ai_evaluation_dashboard' => env('FEATURE_AI_EVALUATION_DASHBOARD', false),
    'evaluation' => [
        'retention_days' => env('COMPETITION_EVALUATION_RETENTION_DAYS', 180),
        'small_sample_threshold' => 20,
    ],
    'audit_viewer' => env('FEATURE_COMPETITION_AUDIT_VIEWER', false),
    'data_erasure' => env('FEATURE_COMPETITION_DATA_ERASURE', false),
    'manual_verification' => [
        'retention_days' => env('COMPETITION_MANUAL_VERIFICATION_RETENTION_DAYS', 90),
        'max_snapshot_bytes' => 2097152,
    ],
];
