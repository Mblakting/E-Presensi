<?php

namespace App\Competition\Http;

use App\Competition\Events\AbsenRecorded;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * [DIGIFEST 2026] Middleware Trust Engine Interceptor.
 *
 * Cara kerja:
 * 1. Request lewat → controller lama berjalan normal → response dibuat
 * 2. Middleware menandai response absen sukses
 * 3. Event di-dispatch dari terminate(), setelah response dikirim
 * 4. Response ke user TIDAK berubah sama sekali
 *
 * Controller FaceRecognitionController dan PresensiController TIDAK disentuh.
 */
class TrustEngineMiddleware
{
    private const EVENT_DATA_ATTRIBUTE = 'competition.trust_engine_event';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!config('competition.trust_engine_shadow', false)) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        if (!in_array($routeName, ['presensi.store', 'presensi.face.store'])) {
            return $response;
        }

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);
        if (($data['status'] ?? '') !== 'success') {
            return $response;
        }

        $isFace = $routeName === 'presensi.face.store';
        $nisn = $isFace
            ? (string) $request->input('nisn_match', '')
            : (string) \Illuminate\Support\Facades\Auth::guard('siswa')->id();

        if ($nisn === '') {
            return $response;
        }

        $accuracy = $request->input('gps_accuracy', $request->input('accuracy'));
        $request->attributes->set(self::EVENT_DATA_ATTRIBUTE, [
            'nisn' => $nisn,
            'absenType' => $data['type'] ?? 'in',
            'method' => $isFace ? 'face' : 'gps',
            'faceDistance' => $isFace ? (float) $request->input('confidence', 0.55) : null,
            'lokasi' => $request->input('lokasi'),
            'gpsAccuracy' => is_numeric($accuracy) && is_finite((float) $accuracy) && (float) $accuracy >= 0
                ? (float) $accuracy
                : null,
            'tglPresensi' => now()->toDateString(),
            'jamAbsen' => now()->format('H:i:s'),
        ]);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (!config('competition.trust_engine_shadow', false)) {
            return;
        }

        $eventData = $request->attributes->get(self::EVENT_DATA_ATTRIBUTE);
        if (!is_array($eventData)) {
            return;
        }

        try {
            event(new AbsenRecorded(...$eventData));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[TrustEngine] Middleware error: ' . $e->getMessage());
        }
    }
}
