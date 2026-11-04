<?php

namespace App\Competition\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * [DIGIFEST 2026] Event yang di-fire setelah absen berhasil disimpan.
 * Controller lama TIDAK diubah — event di-dispatch via response middleware.
 */
class AbsenRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string  $nisn,
        public readonly string  $absenType,    // 'in' | 'out'
        public readonly string  $method,       // 'face' | 'gps'
        public readonly ?float  $faceDistance, // Euclidean distance (null jika GPS)
        public readonly ?string $lokasi,       // "lat,lon"
        public readonly string  $tglPresensi,
        public readonly string  $jamAbsen,
    ) {}
}
