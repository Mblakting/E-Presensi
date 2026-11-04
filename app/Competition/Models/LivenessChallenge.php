<?php

namespace App\Competition\Models;

use Illuminate\Database\Eloquent\Model;

class LivenessChallenge extends Model
{
    protected $table = 'competition_liveness_challenges';

    protected $fillable = [
        'nonce_hash',
        'siswa_nisn',
        'session_hash',
        'instruction',
        'expires_at',
        'used_at',
        'liveness_status',
        'score',
        'score_face',
        'score_gps',
        'score_consistency',
        'score_liveness',
        'verdict',
        'reason_code',
        'reason',
        'attendance_recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'attendance_recorded_at' => 'datetime',
        ];
    }
}
