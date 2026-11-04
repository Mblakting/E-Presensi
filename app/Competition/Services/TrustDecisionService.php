<?php

namespace App\Competition\Services;

use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrustDecisionService
{
    public function evaluate(Request $request, string $nisn, string $instruction, string $livenessStatus, array $evidence): array
    {
        $weights = config('competition.liveness_challenge.weights', []);
        $thresholds = config('competition.liveness_challenge.thresholds', []);
        $reviewThreshold = (int) ($thresholds['review'] ?? 40);
        $faceScore = $this->faceScore($request->input('confidence'), (int) ($weights['face'] ?? 30));
        $gpsScore = $this->gpsScore($request->input('lokasi'), (int) ($weights['gps'] ?? 25));
        $consistencyScore = $this->consistencyScore($nisn, (int) ($weights['consistency'] ?? 15));
        $livenessScore = $livenessStatus === 'passed' ? (int) ($weights['liveness'] ?? 30) : 0;
        $score = $faceScore + $gpsScore + $consistencyScore + $livenessScore;

        $validProof = $livenessStatus === 'passed'
            && $this->proofIsValid($instruction, $evidence, $thresholds);

        if ($livenessStatus === 'failed' || ($livenessStatus === 'passed' && ! $validProof)) {
            return $this->result(
                'rejected',
                'liveness_failed',
                'Wajah cocok, tetapi instruksi liveness tidak berhasil diverifikasi.',
                $livenessStatus,
                $score,
                $faceScore,
                $gpsScore,
                $consistencyScore,
                $livenessScore
            );
        }

        if ($livenessStatus === 'unavailable') {
            if ($faceScore < (int) ($thresholds['face_min'] ?? 18)) {
                return $this->result(
                    'rejected',
                    'face_signal_too_low',
                    'Sinyal kecocokan wajah terlalu rendah untuk jalur verifikasi manual.',
                    $livenessStatus,
                    $score,
                    $faceScore,
                    $gpsScore,
                    $consistencyScore,
                    $livenessScore
                );
            }
            if ($gpsScore < (int) ($thresholds['gps_min'] ?? 15)) {
                return $this->result(
                    'rejected',
                    'gps_signal_too_low',
                    'Lokasi tidak memenuhi batas minimum untuk jalur verifikasi manual.',
                    $livenessStatus,
                    $score,
                    $faceScore,
                    $gpsScore,
                    $consistencyScore,
                    $livenessScore
                );
            }
            if ($score < $reviewThreshold) {
                return $this->result(
                    'rejected',
                    'score_below_review',
                    'Skor sinyal berada di bawah ambang minimum untuk verifikasi admin.',
                    $livenessStatus,
                    $score,
                    $faceScore,
                    $gpsScore,
                    $consistencyScore,
                    $livenessScore
                );
            }

            return $this->result(
                'review',
                'liveness_unavailable',
                'Kamera atau pemeriksa liveness tidak tersedia; presensi menunggu verifikasi admin.',
                $livenessStatus,
                $score,
                $faceScore,
                $gpsScore,
                $consistencyScore,
                $livenessScore
            );
        }

        $acceptThreshold = (int) ($thresholds['accept'] ?? 70);
        if ($validProof && $score >= $acceptThreshold) {
            return $this->result(
                'accepted',
                'signals_accepted',
                'Challenge liveness berhasil dan skor sinyal memenuhi ambang penerimaan.',
                $livenessStatus,
                $score,
                $faceScore,
                $gpsScore,
                $consistencyScore,
                $livenessScore
            );
        }
        if ($score >= $reviewThreshold) {
            return $this->result(
                'review',
                'score_requires_review',
                'Skor sinyal berada pada rentang verifikasi admin.',
                $livenessStatus,
                $score,
                $faceScore,
                $gpsScore,
                $consistencyScore,
                $livenessScore
            );
        }

        return $this->result(
            'rejected',
            'score_below_review',
            'Skor sinyal berada di bawah ambang minimum untuk presensi.',
            $livenessStatus,
            $score,
            $faceScore,
            $gpsScore,
            $consistencyScore,
            $livenessScore
        );
    }

    private function faceScore(mixed $distance, int $weight): int
    {
        if (! is_numeric($distance) || ! is_finite((float) $distance) || (float) $distance < 0) {
            return 0;
        }

        $distance = (float) $distance;
        $perfect = (float) config('competition.liveness_challenge.face_perfect_distance', 0.20);
        $limit = (float) config('competition.liveness_challenge.face_distance_limit', 0.55);
        if ($distance <= $perfect) {
            return $weight;
        }
        if ($distance >= $limit) {
            return 0;
        }

        return (int) round($weight * (1 - (($distance - $perfect) / ($limit - $perfect))));
    }

    private function gpsScore(?string $location, int $weight): int
    {
        $coordinates = $this->coordinates($location);
        if ($coordinates === null || ($coordinates[0] === 0.0 && $coordinates[1] === 0.0)) {
            return 0;
        }

        $school = Schema::hasTable('school_settings') ? SchoolSetting::query()->first() : null;
        $latitude = $school ? (float) $school->latitude : (float) config('school.latitude', -7.671325);
        $longitude = $school ? (float) $school->longitude : (float) config('school.longitude', 110.589607);
        $radius = $school
            ? (int) $school->radius_meters
            : (int) config('school.radius_meters', 100);

        return $this->distance($latitude, $longitude, $coordinates[0], $coordinates[1]) <= $radius
            ? $weight
            : 0;
    }

    private function consistencyScore(string $nisn, int $weight): int
    {
        $history = DB::table('presensi')
            ->where('nisn', $nisn)
            ->where('tgl_presensi', '<', now()->toDateString())
            ->whereNotNull('jam_in')
            ->orderByDesc('tgl_presensi')
            ->limit(7)
            ->pluck('jam_in');

        if ($history->isEmpty() || $history->count() < 3) {
            return $weight;
        }

        $minutes = $history->map(function (string $time): int {
            [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

            return $hours * 60 + $minutes;
        });
        $average = $minutes->average();
        [$hours, $minutesNow] = array_map('intval', explode(':', now()->format('H:i')));
        $difference = abs(($hours * 60 + $minutesNow) - $average);

        return (int) round($weight * ($difference <= 30 ? 1 : ($difference <= 60 ? 0.75 : ($difference <= 120 ? 0.5 : 0.25))));
    }

    private function proofIsValid(string $instruction, array $evidence, array $thresholds): bool
    {
        $delta = $evidence['delta'] ?? null;
        $elapsed = $evidence['elapsed_ms'] ?? null;
        if (! is_numeric($delta) || ! is_finite((float) $delta) || ! is_numeric($elapsed)) {
            return false;
        }

        $elapsed = (int) $elapsed;
        if ($elapsed < (int) ($thresholds['proof_min_elapsed_ms'] ?? 800)
            || $elapsed > (int) ($thresholds['proof_max_elapsed_ms'] ?? 15000)) {
            return false;
        }

        $delta = (float) $delta;

        return match ($instruction) {
            'blink' => $delta >= (float) ($thresholds['blink_delta_min'] ?? 0.10),
            'turn_left' => $delta <= -(float) ($thresholds['turn_delta_min'] ?? 0.12),
            'turn_right' => $delta >= (float) ($thresholds['turn_delta_min'] ?? 0.12),
            default => false,
        };
    }

    private function coordinates(?string $location): ?array
    {
        if ($location === null) {
            return null;
        }

        $parts = explode(',', $location);
        if (count($parts) !== 2 || ! is_numeric(trim($parts[0])) || ! is_numeric(trim($parts[1]))) {
            return null;
        }
        $latitude = (float) trim($parts[0]);
        $longitude = (float) trim($parts[1]);
        if (! is_finite($latitude) || ! is_finite($longitude)
            || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [$latitude, $longitude];
    }

    private function distance(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitude2 - $latitude1);
        $longitudeDelta = deg2rad($longitude2 - $longitude1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function result(
        string $verdict,
        string $reasonCode,
        string $reason,
        string $livenessStatus,
        int $score,
        int $face,
        int $gps,
        int $consistency,
        int $liveness
    ): array {
        return [
            'verdict' => $verdict,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'liveness_status' => $livenessStatus,
            'score' => max(0, min(100, $score)),
            'score_face' => $face,
            'score_gps' => $gps,
            'score_consistency' => $consistency,
            'score_liveness' => $liveness,
        ];
    }
}
