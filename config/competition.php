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

    /*
     * Phase 2: Trust Engine berjalan diam-diam (shadow mode).
     * Menghitung trust score tiap absen tanpa mengubah hasil absen asli.
     */
    'trust_engine_shadow' => env('FEATURE_TRUST_ENGINE_SHADOW', false),

    /*
     * Phase 3: Trust Engine enforce mode.
     * Skor rendah → tolak, menengah → antrean verifikasi admin, tinggi → terima.
     * Hanya aktif jika trust_engine_shadow juga true.
     */
    'trust_engine_enforce' => env('FEATURE_TRUST_ENGINE_ENFORCE', false),

    /*
     * Phase 4: Halaman AI Evaluation Dashboard (metrik FAR/FRR, audit log).
     */
    'ai_evaluation_dashboard' => env('FEATURE_AI_EVALUATION_DASHBOARD', false),

    /*
     * Phase 5: Anomaly Detection — deteksi pola bolos/terlambat.
     */
    'anomaly_detection' => env('FEATURE_ANOMALY_DETECTION', false),

    /*
     * Master switch: tampilkan menu navigasi fitur lomba di sidebar admin.
     * Jika false, tidak ada UI lomba yang terlihat sama sekali.
     */
    'competition_menu' => env('FEATURE_COMPETITION_MENU', false),

];
