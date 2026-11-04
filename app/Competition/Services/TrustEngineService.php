<?php

namespace App\Competition\Services;

use App\Competition\Models\TrustScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * [DIGIFEST 2026] Trust Engine Service
 *
 * Menghitung Trust Score (0-100) dari 3 sinyal:
 *   - Face Distance  (0-40 poin): seberapa dekat wajah dengan descriptor terdaftar
 *   - GPS Accuracy   (0-40 poin): apakah koordinat masuk akal & dalam radius
 *   - Consistency    (0-20 poin): konsistensi pola absen historis siswa
 *
 * Threshold verdict:
 *   >= 70 → accepted
 *   40-69 → queued (verifikasi manual admin)
 *   < 40  → rejected
 *
 * Shadow mode: simpan skor tapi TIDAK mengubah hasil absen asli.
 * Enforce mode: skor menentukan apakah absen diterima/ditolak.
 */
class TrustEngineService
{
    // Threshold enforce mode
    const THRESHOLD_ACCEPT = 70;
    const THRESHOLD_QUEUE  = 40;

    // Face distance: 0.0 = sempurna, 0.55 = batas minimum sistem lama
    const FACE_DIST_PERFECT = 0.20;
    const FACE_DIST_LIMIT   = 0.55;

    /**
     * Hitung dan simpan Trust Score untuk satu event absen.
     * Mengembalikan array hasil untuk dipakai enforce mode.
     */
    public function evaluate(
        string  $nisn,
        string  $absenType,
        string  $method,
        ?float  $faceDistance,
        ?string $lokasi,
        string  $tglPresensi,
        string  $jamAbsen,
        bool    $isShadow = true
    ): array {
        try {
            $scoreFace        = $this->scoreFaceDistance($faceDistance, $method);
            $scoreGps         = $this->scoreGps($lokasi);
            $scoreConsistency = $this->scoreConsistency($nisn, $tglPresensi, $jamAbsen);

            $total   = $scoreFace + $scoreGps + $scoreConsistency;
            $verdict = $this->verdict($total);
            $reason  = $this->buildReason($verdict, $scoreFace, $scoreGps, $scoreConsistency, $faceDistance);

            TrustScore::create([
                'nisn'                => $nisn,
                'score'               => $total,
                'absen_type'          => $absenType,
                'method'              => $method,
                'score_face_distance' => $scoreFace,
                'score_gps_accuracy'  => $scoreGps,
                'score_consistency'   => $scoreConsistency,
                'verdict'             => $verdict,
                'reason'              => $reason,
                'raw_signals'         => [
                    'face_distance' => $faceDistance,
                    'lokasi'        => $lokasi,
                    'jam_absen'     => $jamAbsen,
                    'method'        => $method,
                ],
                'is_shadow' => $isShadow,
            ]);

            return [
                'score'   => $total,
                'verdict' => $verdict,
                'reason'  => $reason,
                'components' => [
                    'face'        => $scoreFace,
                    'gps'         => $scoreGps,
                    'consistency' => $scoreConsistency,
                ],
            ];

        } catch (\Throwable $e) {
            // Trust Engine TIDAK BOLEH crash dan mengganggu absen
            Log::warning('[TrustEngine] Gagal evaluasi, diabaikan: ' . $e->getMessage());
            return ['score' => 50, 'verdict' => TrustScore::VERDICT_PENDING, 'reason' => 'engine_error'];
        }
    }

    // ── Komponen Skor ────────────────────────────────────────────────────────

    /**
     * Skor face distance: 0-40 poin.
     * Metode GPS → skor netral 20 (tidak ada data wajah).
     */
    private function scoreFaceDistance(?float $distance, string $method): int
    {
        if ($method !== 'face' || $distance === null) {
            return 20; // netral untuk absen GPS
        }

        if ($distance <= self::FACE_DIST_PERFECT) return 40;
        if ($distance >= self::FACE_DIST_LIMIT)   return 0;

        // Interpolasi linear antara perfect dan limit
        $ratio = ($distance - self::FACE_DIST_PERFECT) / (self::FACE_DIST_LIMIT - self::FACE_DIST_PERFECT);
        return (int) round(40 * (1 - $ratio));
    }

    /**
     * Skor GPS: 0-40 poin.
     * Validasi format koordinat dan apakah masuk akal (tidak 0,0).
     */
    private function scoreGps(?string $lokasi): int
    {
        if (empty($lokasi)) return 0;

        $parts = explode(',', $lokasi);
        if (count($parts) !== 2) return 0;

        $lat = (float) trim($parts[0]);
        $lon = (float) trim($parts[1]);

        // Koordinat 0,0 = tidak valid (default browser saat GPS gagal)
        if ($lat === 0.0 && $lon === 0.0) return 0;

        // Koordinat di luar range valid
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) return 0;

        // Koordinat valid → skor penuh
        // (validasi radius sudah dilakukan di HasversineDistance, tidak perlu duplikasi)
        return 40;
    }

    /**
     * Skor konsistensi historis: 0-20 poin.
     * Cek apakah jam absen konsisten dengan pola 7 hari terakhir.
     */
    private function scoreConsistency(string $nisn, string $tglPresensi, string $jamAbsen): int
    {
        try {
            // Ambil 7 record absen terakhir (tidak termasuk hari ini)
            $history = DB::table('presensi')
                ->where('nisn', $nisn)
                ->where('tgl_presensi', '<', $tglPresensi)
                ->whereNotNull('jam_in')
                ->orderBy('tgl_presensi', 'desc')
                ->limit(7)
                ->pluck('jam_in');

            if ($history->count() < 3) return 15; // data tidak cukup → skor netral

            // Hitung rata-rata jam masuk dalam menit
            $avgMinutes = $history->map(function ($jam) {
                [$h, $m] = explode(':', $jam);
                return (int)$h * 60 + (int)$m;
            })->average();

            // Jam absen sekarang dalam menit
            [$h, $m] = explode(':', substr($jamAbsen, 0, 5));
            $nowMinutes = (int)$h * 60 + (int)$m;

            $diff = abs($nowMinutes - $avgMinutes);

            // Dalam 30 menit dari rata-rata → konsisten
            if ($diff <= 30)  return 20;
            if ($diff <= 60)  return 15;
            if ($diff <= 120) return 10;
            return 5; // sangat tidak konsisten

        } catch (\Throwable $e) {
            return 10; // fallback netral
        }
    }

    // ── Verdict & Reason ─────────────────────────────────────────────────────

    private function verdict(int $score): string
    {
        if ($score >= self::THRESHOLD_ACCEPT) return TrustScore::VERDICT_ACCEPTED;
        if ($score >= self::THRESHOLD_QUEUE)  return TrustScore::VERDICT_QUEUED;
        return TrustScore::VERDICT_REJECTED;
    }

    private function buildReason(string $verdict, int $face, int $gps, int $consistency, ?float $dist): string
    {
        if ($verdict === TrustScore::VERDICT_ACCEPTED) {
            return 'Semua sinyal valid. Skor: wajah=' . $face . ' gps=' . $gps . ' konsistensi=' . $consistency;
        }

        $issues = [];
        if ($face < 20)        $issues[] = 'kecocokan wajah rendah (jarak=' . round($dist ?? 0, 3) . ')';
        if ($gps < 20)         $issues[] = 'sinyal GPS tidak valid';
        if ($consistency < 10) $issues[] = 'pola jam absen tidak konsisten';

        $prefix = $verdict === TrustScore::VERDICT_QUEUED ? 'Perlu verifikasi' : 'Ditolak';
        return $prefix . ': ' . (empty($issues) ? 'skor gabungan rendah' : implode(', ', $issues));
    }
}
