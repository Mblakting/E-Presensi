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
 * 2. Middleware intercept response SETELAH controller selesai
 * 3. Jika response adalah absen sukses → dispatch AbsenRecorded event
 * 4. Response ke user TIDAK berubah sama sekali
 *
 * Controller FaceRecognitionController dan PresensiController TIDAK disentuh.
 */
class TrustEngineMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Hanya proses jika flag aktif
        if (!config('competition.trust_engine_shadow', false)) {
            return $response;
        }

        // Hanya proses route absen
        $routeName = $request->route()?->getName();
        if (!in_array($routeName, ['presensi.store', 'presensi.face.store'])) {
            return $response;
        }

        // Hanya proses jika response sukses
        if (!($response instanceof JsonResponse)) {
            return $response;
        }

        $data = $response->getData(true);
        if (($data['status'] ?? '') !== 'success') {
            return $response;
        }

        // Ekstrak sinyal dari request
        try {
            $isFace      = $routeName === 'presensi.face.store';
            $method      = $isFace ? 'face' : 'gps';
            $absenType   = $data['type'] ?? 'in';
            $lokasi      = $request->input('lokasi', '');
            $faceDistance = $isFace ? (float) $request->input('confidence', 0.55) : null;

            $nisn = $isFace
                ? (string) $request->input('nisn_match', '')
                : (string) \Illuminate\Support\Facades\Auth::guard('siswa')->id();

            if (empty($nisn)) return $response;

            AbsenRecorded::dispatch(
                nisn:         $nisn,
                absenType:    $absenType,
                method:       $method,
                faceDistance: $faceDistance,
                lokasi:       $lokasi,
                tglPresensi:  now()->toDateString(),
                jamAbsen:     now()->format('H:i:s'),
            );

        } catch (\Throwable $e) {
            // Middleware TIDAK BOLEH crash dan mengganggu response
            \Illuminate\Support\Facades\Log::warning('[TrustEngine] Middleware error: ' . $e->getMessage());
        }

        return $response;
    }
}
