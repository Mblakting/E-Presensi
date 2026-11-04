<?php

namespace App\Competition\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualVerification extends Model
{
    protected $table = 'competition_manual_verifications';

    protected $fillable = [
        'challenge_id',
        'trust_score_id',
        'siswa_nisn',
        'score',
        'reason_code',
        'reason',
        'absen_type',
        'attendance_date',
        'attendance_time',
        'lokasi',
        'confidence',
        'foto_tangkapan',
        'status',
        'admin_id',
        'catatan',
        'attempted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'attempted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(LivenessChallenge::class, 'challenge_id');
    }

    public function trustScore(): BelongsTo
    {
        return $this->belongsTo(TrustScore::class, 'trust_score_id');
    }
}
