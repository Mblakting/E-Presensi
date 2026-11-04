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
            'accept' => 70,
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
