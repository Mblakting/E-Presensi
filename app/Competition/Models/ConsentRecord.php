<?php

namespace App\Competition\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    protected $table = 'competition_consents';

    protected $fillable = [
        'siswa_nisn',
        'status',
        'text_version',
        'text_hash',
        'actor_type',
        'actor_id',
        'authority_basis',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }
}
