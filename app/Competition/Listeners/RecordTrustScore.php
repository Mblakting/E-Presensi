<?php

namespace App\Competition\Listeners;

use App\Competition\Events\AbsenRecorded;
use App\Competition\Services\TrustEngineService;
use Illuminate\Support\Facades\Log;

/**
 * [DIGIFEST 2026] Listener untuk Trust Engine Shadow Mode.
 *
 * Dipanggil setelah AbsenRecorded event di-fire.
 * Hanya aktif jika FEATURE_TRUST_ENGINE_SHADOW=true.
 * Tidak mengubah hasil absen, tidak mengubah response ke user.
 */
class RecordTrustScore
{
    public function __construct(private TrustEngineService $engine) {}

    public function handle(AbsenRecorded $event): void
    {
        // Double-check flag — listener tidak boleh jalan jika flag mati
        if (!config('competition.trust_engine_shadow', false)) {
            return;
        }

        $isShadow = !config('competition.trust_engine_enforce', false);

        $result = $this->engine->evaluate(
            nisn:        $event->nisn,
            absenType:   $event->absenType,
            method:      $event->method,
            faceDistance: $event->faceDistance,
            lokasi:      $event->lokasi,
            tglPresensi: $event->tglPresensi,
            jamAbsen:    $event->jamAbsen,
            isShadow:    $isShadow,
        );

        Log::info('[TrustEngine][Shadow] NISN=' . $event->nisn
            . ' score=' . $result['score']
            . ' verdict=' . $result['verdict']
            . ' method=' . $event->method);
    }
}
