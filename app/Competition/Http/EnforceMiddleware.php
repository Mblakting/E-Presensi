<?php

namespace App\Competition\Http;

use App\Competition\Models\AuditLog;
use App\Competition\Models\LivenessChallenge;
use App\Competition\Models\VerificationQueueItem;
use App\Competition\Services\LivenessChallengeService;
use App\Competition\Services\TrustDecisionService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $response = $next($request);
        if ($decision['verdict'] !== 'review' || ! $this->attendanceSucceeded($response)) {
            return $response;
        }

        try {
            $this->createReviewQueueItem($challenge, $decision, $response);
        } catch (\Throwable $exception) {
            Log::error('[Competition] Attendance succeeded but verification queue insertion failed.', [
                'challenge_id' => $challenge->id,
                'exception_class' => $exception::class,
            ]);
            $response->setData(array_merge($response->getData(true), [
                'message' => 'Presensi tercatat, tetapi antrean verifikasi gagal disimpan. Hubungi admin sekolah.',
                'verification_status' => 'queue_error',
            ]));

            return $response;
        }

        $data = $response->getData(true);
        $data['message'] = 'Presensi tercatat dan menunggu verifikasi admin.';
        $data['verification_status'] = 'pending';
        $response->setData($data);

        return $response;
    }

    private function attendanceSucceeded(Response $response): bool
    {
        if (! $response instanceof JsonResponse || $response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return false;
        }

        return ($response->getData(true)['status'] ?? null) === 'success';
    }

    private function createReviewQueueItem(LivenessChallenge $challenge, array $decision, JsonResponse $response): void
    {
        DB::transaction(function () use ($challenge, $decision, $response): void {
            $attendanceType = $response->getData(true)['type'] ?? 'in';
            VerificationQueueItem::query()->create([
                'challenge_id' => $challenge->id,
                'siswa_nisn' => $challenge->siswa_nisn,
                'absen_type' => in_array($attendanceType, ['in', 'out'], true) ? $attendanceType : 'in',
                'score' => $decision['score'],
                'reason_code' => $decision['reason_code'],
                'reason' => $decision['reason'],
                'status' => 'pending',
            ]);

            LivenessChallenge::query()->whereKey($challenge->id)->update([
                'attendance_recorded_at' => now(),
            ]);

            AuditLog::record(
                'verification_queued',
                'system',
                null,
                $challenge->siswa_nisn,
                ['challenge_id' => $challenge->id, 'score' => $decision['score']],
                str_starts_with($challenge->siswa_nisn, '9999')
            );
        });
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
