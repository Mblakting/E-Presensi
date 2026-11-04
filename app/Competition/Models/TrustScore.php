<?php

namespace App\Competition\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * [DIGIFEST 2026] Model untuk tabel competition_trust_scores.
 * Namespace terpisah agar tidak konflik dengan model klien.
 */
class TrustScore extends Model
{
    protected $table = 'competition_trust_scores';

    protected $fillable = [
        'nisn',
        'score',
        'absen_type',
        'method',
        'score_face_distance',
        'score_gps_accuracy',
        'score_consistency',
        'verdict',
        'reason',
        'raw_signals',
        'is_shadow',
    ];

    protected function casts(): array
    {
        return [
            'raw_signals' => 'array',
            'is_shadow'   => 'boolean',
        ];
    }

    // Verdict constants
    const VERDICT_ACCEPTED = 'accepted';
    const VERDICT_QUEUED   = 'queued';
    const VERDICT_REJECTED = 'rejected';
    const VERDICT_PENDING  = 'pending';
}
