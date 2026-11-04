<?php

namespace App\Competition\Http;

use App\Competition\Models\LivenessChallenge;
use App\Competition\Services\ConsentService;
use App\Competition\Services\LivenessChallengeService;
use App\Competition\Services\ManualVerificationService;
use App\Competition\Services\TrustDecisionService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnforceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('competition.trust_engine_enforce', false)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName === 'presensi.face') {
            return $this->injectScript($request, $next);
        }
        if ($routeName !== 'presensi.face.store') {
            return $next($request);
        }

        return $this->enforceAttendance($request, $next);
    }

    private function injectScript(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $contentType = (string) $response->headers->get('Content-Type', '');
        $content = $response->getContent();
        if (! str_contains(strtolower($contentType), 'text/html') || ! is_string($content)) {
            return $response;
        }

        $scriptUrl = htmlspecialchars(route('competition.liveness.script'), ENT_QUOTES, 'UTF-8');
        $script = '<script defer src="'.$scriptUrl.'"></script>';
        $updated = preg_replace_callback(
            '/<\/body\s*>/i',
            fn (array $matches): string => $script.$matches[0],
            $content,
            1,
            $replacements
        );

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        if ($replacements !== 1 || ! is_string($updated)) {
            Log::warning('[Competition] Liveness script was not injected: attendance HTML has no closing body tag.');

            return $response;
        }

        $response->headers->remove('Content-Length');
        $response->headers->remove('ETag');
        $response->setContent($updated);

        return $response;
    }

    private function enforceAttendance(Request $request, Closure $next): Response
    {
        $nisn = Auth::guard('siswa')->id();
        if (! is_string($nisn) || $nisn === '') {
            return $this->failure('student_auth_required', 'Sesi siswa tidak ditemukan. Silakan masuk kembali.', 401);
        }
        if (config('competition.consent_gate', false)
            && ! app(ConsentService::class)->isGranted($nisn)) {
            return $this->failure(
                'face_consent_required',
                'Persetujuan pemrosesan wajah belum aktif. Gunakan presensi non-wajah atau hubungi pengelola.',
                403
            );
        }

        $nonce = $request->input('challenge_nonce');
        if (! is_string($nonce) || $nonce === '') {
            return $this->failure('challenge_unavailable', 'Challenge keamanan tidak tersedia. Muat ulang halaman dan coba lagi.', 422);
        }

        try {
            $consumed = app(LivenessChallengeService::class)->consume($nonce, $nisn, $request->session()->getId());
        } catch (\Throwable $exception) {
            Log::error('[Competition] Failed to validate liveness challenge.', [
                'exception_class' => $exception::class,
            ]);

            return $this->failure('challenge_unavailable', 'Challenge keamanan tidak dapat diverifikasi. Coba lagi nanti.', 503);
        }

        if (! $consumed['ok']) {
            [$status, $message] = match ($consumed['error']) {
                'challenge_expired' => [422, 'Challenge kedaluwarsa. Muat ulang halaman dan coba lagi.'],
                'challenge_replayed' => [409, 'Challenge sudah pernah digunakan. Muat ulang halaman untuk mencoba lagi.'],
                'challenge_owner_mismatch' => [403, 'Challenge tidak terkait dengan sesi siswa ini.'],
                default => [422, 'Challenge tidak valid. Muat ulang halaman dan coba lagi.'],
            };

            return $this->failure($consumed['error'], $message, $status);
        }

        $challenge = $consumed['challenge'];
        $status = $request->input('liveness_status');
        $evidence = $request->input('liveness_evidence', []);
        if (! in_array($status, ['passed', 'failed', 'unavailable'], true) || ! is_array($evidence)) {
            $decision = [
                'verdict' => 'rejected',
                'reason_code' => 'liveness_proof_invalid',
                'reason' => 'Bukti challenge tidak lengkap atau tidak valid.',
                'liveness_status' => 'failed',
                'score' => 0,
                'score_face' => 0,
                'score_gps' => 0,
                'score_consistency' => 0,
                'score_liveness' => 0,
            ];
        } else {
            try {
                $decision = app(TrustDecisionService::class)->evaluate(
                    $request,
                    $nisn,
                    $challenge->instruction,
                    $status,
                    $evidence
                );
            } catch (\Throwable $exception) {
                $this->recordEvaluationFailure($challenge, $nisn, $exception);

                return $this->failure(
                    'evaluation_unavailable',
                    'Pemeriksaan keamanan tidak dapat diselesaikan. Tidak ada presensi yang dicatat; silakan coba lagi.',
                    503
                );
            }
        }

        try {
            $recorded = app(LivenessChallengeService::class)->recordDecision($challenge, $decision);
            $challenge = $recorded['challenge'];
            $decision = $recorded['decision'];
        } catch (\Throwable $exception) {
            Log::error('[Competition] Failed to record liveness decision.', [
                'challenge_id' => $challenge->id,
                'exception_class' => $exception::class,
            ]);

            return $this->failure(
                'evaluation_unavailable',
                'Hasil pemeriksaan keamanan tidak dapat disimpan. Tidak ada presensi yang dicatat; silakan coba lagi.',
                503
            );
        }

        if ($decision['verdict'] === 'rejected') {
            return $this->failure($decision['reason_code'], $decision['reason'], 422);
        }

        if ($decision['verdict'] === 'review') {
            try {
                app(ManualVerificationService::class)->createPending($challenge, $decision, $nisn, $request->all());
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?? 'Data percobaan presensi tidak valid.';

                return $this->failure('manual_verification_invalid', $message, 422);
            } catch (\Throwable $exception) {
                Log::error('[Competition] Failed to create manual verification item.', [
                    'challenge_id' => $challenge->id,
                    'exception_class' => $exception::class,
                ]);

                return $this->failure(
                    'manual_verification_unavailable',
                    'Percobaan presensi tidak dapat disimpan untuk verifikasi. Tidak ada presensi yang dicatat; silakan coba lagi.',
                    503
                );
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Presensi memerlukan verifikasi manual admin. Mohon tunggu.',
                'verification_status' => 'pending',
            ]);
        }

        $response = $next($request);

        return $response;
    }

    private function recordEvaluationFailure(LivenessChallenge $challenge, string $nisn, \Throwable $exception): void
    {
        Log::error('[Competition] Trust decision evaluation failed.', [
            'challenge_id' => $challenge->id,
            'exception_class' => $exception::class,
        ]);

        try {
            $challenge->update([
                'verdict' => 'rejected',
                'reason_code' => 'evaluation_unavailable',
                'reason' => 'Pemeriksaan keamanan tidak tersedia.',
            ]);
        } catch (\Throwable $recordException) {
            Log::error('[Competition] Failed to persist evaluation failure.', [
                'challenge_id' => $challenge->id,
                'exception_class' => $recordException::class,
            ]);
        }
    }

    private function failure(string $type, string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'type' => $type,
            'message' => $message,
        ], $status);
    }
}
