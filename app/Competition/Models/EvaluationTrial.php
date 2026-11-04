<?php

namespace App\Competition\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationTrial extends Model
{
    protected $table = 'competition_evaluation_trials';

    protected $fillable = [
        'trial_type',
        'outcome',
        'reason',
        'tester_code',
        'tested_at',
        'notes',
        'fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'tested_at' => 'date',
        ];
    }

    /**
     * @param  array{trial_type: string, outcome: string, reason: string, tester_code: string, tested_at: string, notes: ?string}  $trial
     */
    public static function fingerprintFor(array $trial): string
    {
        return hash('sha256', json_encode($trial, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
