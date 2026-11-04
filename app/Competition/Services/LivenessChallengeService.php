<?php

namespace App\Competition\Services;

use App\Competition\Models\LivenessChallenge;
use Illuminate\Support\Facades\DB;

class LivenessChallengeService
{
    private const INSTRUCTIONS = ['blink', 'turn_left', 'turn_right'];

    public function issue(string $nisn, string $sessionId): array
    {
        $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $instruction = self::INSTRUCTIONS[random_int(0, count(self::INSTRUCTIONS) - 1)];
        $ttl = max(10, (int) config('competition.liveness_challenge.ttl_seconds', 90));

        LivenessChallenge::query()->create([
            'nonce_hash' => hash('sha256', $nonce),
            'siswa_nisn' => $nisn,
            'session_hash' => hash('sha256', $sessionId),
            'instruction' => $instruction,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return [
            'nonce' => $nonce,
            'instruction' => $instruction,
            'expires_in' => $ttl,
            'proof_thresholds' => [
                'blink_delta_min' => (float) config('competition.liveness_challenge.thresholds.blink_delta_min', 0.10),
                'turn_delta_min' => (float) config('competition.liveness_challenge.thresholds.turn_delta_min', 0.12),
                'proof_min_elapsed_ms' => (int) config('competition.liveness_challenge.thresholds.proof_min_elapsed_ms', 800),
                'proof_max_elapsed_ms' => (int) config('competition.liveness_challenge.thresholds.proof_max_elapsed_ms', 15000),
            ],
        ];
    }

    public function consume(string $nonce, string $nisn, string $sessionId): array
    {
        return DB::transaction(function () use ($nonce, $nisn, $sessionId): array {
            DB::table('siswa')->where('nisn', $nisn)->lockForUpdate()->first();

            $challenge = LivenessChallenge::query()
                ->where('nonce_hash', hash('sha256', $nonce))
                ->lockForUpdate()
                ->first();

            if (! $challenge) {
                return ['ok' => false, 'error' => 'challenge_invalid'];
            }

            if ($challenge->siswa_nisn !== $nisn || ! hash_equals($challenge->session_hash, hash('sha256', $sessionId))) {
                return ['ok' => false, 'error' => 'challenge_owner_mismatch'];
            }

            if ($challenge->used_at !== null) {
                return ['ok' => false, 'error' => 'challenge_replayed'];
            }

            if ($challenge->expires_at->isPast()) {
                $challenge->update([
                    'used_at' => now(),
                    'verdict' => 'rejected',
                    'reason_code' => 'challenge_expired',
                    'reason' => 'Challenge kedaluwarsa. Muat ulang halaman dan coba lagi.',
                ]);

                return ['ok' => false, 'error' => 'challenge_expired'];
            }

            $challenge->update(['used_at' => now()]);

            return ['ok' => true, 'challenge' => $challenge->fresh()];
        });
    }

    public function recordDecision(LivenessChallenge $challenge, array $decision): array
    {
        return DB::transaction(function () use ($challenge, $decision): array {
            DB::table('siswa')->where('nisn', $challenge->siswa_nisn)->lockForUpdate()->first();
            $current = LivenessChallenge::query()->lockForUpdate()->findOrFail($challenge->id);

            if ($decision['reason_code'] === 'liveness_unavailable') {
                $startOfDay = now()->startOfDay();
                $usedFallbacks = LivenessChallenge::query()
                    ->where('siswa_nisn', $current->siswa_nisn)
                    ->where('reason_code', 'liveness_unavailable')
                    ->where('used_at', '>=', $startOfDay)
                    ->where('id', '<>', $current->id)
                    ->count();

                $limit = max(0, (int) config('competition.liveness_challenge.thresholds.unavailable_daily_limit', 2));
                if ($usedFallbacks >= $limit) {
                    $decision = array_merge($decision, [
                        'verdict' => 'rejected',
                        'reason_code' => 'liveness_unavailable_daily_limit',
                        'reason' => 'Batas harian untuk verifikasi kamera tidak tersedia telah tercapai.',
                    ]);
                }
            }

            $current->update([
                'liveness_status' => $decision['liveness_status'],
                'score' => $decision['score'],
                'score_face' => $decision['score_face'],
                'score_gps' => $decision['score_gps'],
                'score_consistency' => $decision['score_consistency'],
                'score_liveness' => $decision['score_liveness'],
                'verdict' => $decision['verdict'],
                'reason_code' => $decision['reason_code'],
                'reason' => $decision['reason'],
            ]);

            return ['challenge' => $current->fresh(), 'decision' => $decision];
        });
    }
}
