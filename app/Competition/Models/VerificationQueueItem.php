<?php

namespace App\Competition\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationQueueItem extends Model
{
    protected $table = 'competition_verification_queue';

    protected $fillable = [
        'challenge_id',
        'siswa_nisn',
        'absen_type',
        'score',
        'reason_code',
        'reason',
        'status',
        'reviewer_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
}
