<?php

namespace App\Competition\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * [DIGIFEST 2026] Model untuk tabel competition_audit_logs.
 */
class AuditLog extends Model
{
    protected $table = 'competition_audit_logs';

    protected $fillable = [
        'event_type',
        'actor_type',
        'actor_id',
        'subject_nisn',
        'payload_hash',
        'ip_address',
        'user_agent',
        'is_synthetic',
    ];

    protected function casts(): array
    {
        return [
            'is_synthetic' => 'boolean',
        ];
    }

    /**
     * Catat event ke audit log.
     */
    public static function record(string $eventType, string $actorType, ?string $actorId, ?string $subjectNisn, array $payload = [], bool $isSynthetic = false): void
    {
        static::create([
            'event_type'    => $eventType,
            'actor_type'    => $actorType,
            'actor_id'      => $actorId,
            'subject_nisn'  => $subjectNisn,
            'payload_hash'  => hash('sha256', json_encode($payload)),
            'ip_address'    => request()->ip(),
            'user_agent'    => substr(request()->userAgent() ?? '', 0, 255),
            'is_synthetic'  => $isSynthetic,
        ]);
    }
}
