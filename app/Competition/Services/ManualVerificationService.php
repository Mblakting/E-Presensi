<?php

namespace App\Competition\Services;

use App\Competition\Models\AuditLog;
use App\Competition\Models\LivenessChallenge;
use App\Competition\Models\ManualVerification;
use App\Competition\Models\TrustScore;
use App\Traits\HasversineDistance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ManualVerificationService
{
    use HasversineDistance;

    public function createPending(
        LivenessChallenge $challenge,
        array $decision,
        string $authenticatedNisn,
        array $input
    ): ManualVerification {
        $nisnMatch = $input['nisn_match'] ?? null;
        $confidence = $input['confidence'] ?? null;
        $location = $input['lokasi'] ?? null;
        $image = $input['image'] ?? null;

        if (! is_string($nisnMatch) || ! hash_equals($authenticatedNisn, $nisnMatch)) {
            throw ValidationException::withMessages(['nisn_match' => 'Identitas siswa tidak cocok dengan sesi yang aktif.']);
        }
        if (! is_numeric($confidence) || ! is_finite((float) $confidence) || (float) $confidence < 0 || (float) $confidence > 0.55) {
            throw ValidationException::withMessages(['confidence' => 'Sinyal kecocokan wajah tidak valid.']);
        }
        if (! is_string($location) || ! $this->validateSchoolRadius($location)['valid']) {
            throw ValidationException::withMessages(['lokasi' => 'Lokasi tidak memenuhi batas geofence sekolah.']);
        }

        $imageBytes = $this->decodeSnapshot($image);
        $today = now()->toDateString();
        $path = 'competition/manual-verifications/'.$challenge->id.'.jpg';
        $disk = Storage::disk('local');
        if (! $disk->put($path, $imageBytes)) {
            throw new RuntimeException('Could not store the private attendance snapshot.');
        }

        try {
            return DB::transaction(function () use ($challenge, $decision, $authenticatedNisn, $confidence, $location, $path, $today): ManualVerification {
                DB::table('siswa')->where('nisn', $authenticatedNisn)->lockForUpdate()->first();
                $attendance = DB::table('presensi')
                    ->where('nisn', $authenticatedNisn)
                    ->where('tgl_presensi', $today)
                    ->lockForUpdate()
                    ->first();
                if ($attendance && ! empty($attendance->jam_out)) {
                    throw ValidationException::withMessages(['attendance' => 'Presensi masuk dan pulang hari ini sudah tercatat.']);
                }
                if (ManualVerification::query()
                    ->where('siswa_nisn', $authenticatedNisn)
                    ->whereDate('attendance_date', $today)
                    ->where('status', 'pending')
                    ->exists()) {
                    throw ValidationException::withMessages(['attendance' => 'Percobaan presensi hari ini sudah menunggu verifikasi admin.']);
                }

                $trustScore = TrustScore::query()->create([
                    'nisn' => $authenticatedNisn,
                    'score' => $decision['score'],
                    'absen_type' => $attendance ? 'out' : 'in',
                    'method' => 'face',
                    'score_face_distance' => $decision['score_face'],
                    'score_gps_accuracy' => $decision['score_gps'],
                    'score_consistency' => $decision['score_consistency'],
                    'verdict' => TrustScore::VERDICT_QUEUED,
                    'reason' => $decision['reason'],
                    'raw_signals' => [
                        'source' => 'phase3_liveness_challenge',
                        'challenge_id' => $challenge->id,
                        'score_liveness' => $decision['score_liveness'],
                    ],
                    'is_shadow' => false,
                ]);

                $row = ManualVerification::query()->create([
                    'challenge_id' => $challenge->id,
                    'trust_score_id' => $trustScore->id,
                    'siswa_nisn' => $authenticatedNisn,
                    'score' => $decision['score'],
                    'reason_code' => $decision['reason_code'],
                    'reason' => $decision['reason'],
                    'absen_type' => $attendance ? 'out' : 'in',
                    'attendance_date' => $today,
                    'attendance_time' => now()->format('H:i:s'),
                    'lokasi' => $location,
                    'confidence' => (float) $confidence,
                    'foto_tangkapan' => $path,
                    'status' => 'pending',
                    'attempted_at' => now(),
                ]);

                AuditLog::record(
                    'manual_verification_queued',
                    'system',
                    null,
                    $authenticatedNisn,
                    ['verification_id' => $row->id, 'challenge_id' => $challenge->id, 'score' => $decision['score']],
                    str_starts_with($authenticatedNisn, '9999')
                );

                return $row;
            });
        } catch (\Throwable $exception) {
            if (! $disk->delete($path) && $disk->exists($path)) {
                Log::error('[Competition] Failed to remove a snapshot after queue creation failed.', [
                    'challenge_id' => $challenge->id,
                ]);
            }
            throw $exception;
        }
    }

    public function decide(int $id, string $decision, string $adminId, ?string $notes = null): ManualVerification
    {
        $snapshotPath = null;
        try {
            $verification = DB::transaction(function () use ($id, $decision, $adminId, $notes, &$snapshotPath): ManualVerification {
                $item = ManualVerification::query()->lockForUpdate()->findOrFail($id);
                abort_unless($item->status === 'pending', 409, 'Item ini sudah ditinjau.');
                DB::table('siswa')->where('nisn', $item->siswa_nisn)->lockForUpdate()->first();

                if ($decision === 'approved') {
                    if (config('competition.consent_gate', false)
                        && ! app(ConsentService::class)->isGranted($item->siswa_nisn)) {
                        abort(409, 'Persetujuan pemrosesan wajah sudah tidak aktif; presensi tidak dicatat.');
                    }
                    if (! $this->validateSchoolRadius($item->lokasi)['valid']) {
                        abort(409, 'Lokasi percobaan tidak lagi memenuhi batas geofence sekolah; presensi tidak dicatat.');
                    }

                    $sameDay = DB::table('presensi')
                        ->where('nisn', $item->siswa_nisn)
                        ->where('tgl_presensi', $item->attendance_date->toDateString())
                        ->lockForUpdate()
                        ->first();
                    if ($sameDay && ! empty($sameDay->jam_out)) {
                        abort(409, 'Presensi hari ini sudah lengkap; antrean tidak dapat disetujui.');
                    }
                    if (($item->absen_type === 'in' && $sameDay)
                        || ($item->absen_type === 'out' && ! $sameDay)) {
                        abort(409, 'Status presensi siswa berubah sejak percobaan; antrean tidak dapat disetujui.');
                    }
                    if ($item->absen_type === 'out' && empty($sameDay->jam_in)) {
                        abort(409, 'Presensi masuk tidak ditemukan; antrean pulang tidak dapat disetujui.');
                    }

                    $privateDisk = Storage::disk('local');
                    if (! $this->isPrivateSnapshotPath($item->foto_tangkapan)
                        || ! $privateDisk->exists($item->foto_tangkapan)) {
                        abort(409, 'Foto tangkapan tidak tersedia; antrean tidak dapat disetujui.');
                    }
                    $imageBytes = $privateDisk->get($item->foto_tangkapan);
                    $snapshotPath = 'uploads/absensi/'.$item->siswa_nisn.'-'.$item->attendance_date->toDateString().'-'.$item->absen_type.'-face.jpg';
                    if (! Storage::disk('public')->put($snapshotPath, $imageBytes)) {
                        throw new RuntimeException('Could not copy the approved attendance snapshot.');
                    }

                    if ($sameDay) {
                        DB::table('presensi')
                            ->where('id', $sameDay->id)
                            ->update([
                                'jam_out' => $item->attendance_time,
                                'foto_out' => basename($snapshotPath),
                                'lokasi_out' => $item->lokasi,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('presensi')->insert([
                            'nisn' => $item->siswa_nisn,
                            'tgl_presensi' => $item->attendance_date->toDateString(),
                            'jam_in' => $item->attendance_time,
                            'foto_in' => basename($snapshotPath),
                            'lokasi_in' => $item->lokasi,
                            'created_at' => $item->attempted_at,
                            'updated_at' => now(),
                        ]);
                    }
                }

                $item->update([
                    'status' => $decision,
                    'admin_id' => $adminId,
                    'catatan' => $notes,
                    'reviewed_at' => now(),
                ]);

                AuditLog::record(
                    'manual_verification_'.$decision,
                    'admin',
                    $adminId,
                    $item->siswa_nisn,
                    ['verification_id' => $item->id, 'attendance_written' => $decision === 'approved'],
                    str_starts_with($item->siswa_nisn, '9999')
                );
                if ($decision === 'approved') {
                    LivenessChallenge::query()->whereKey($item->challenge_id)->update([
                        'attendance_recorded_at' => now(),
                    ]);
                }

                return $item->fresh();
            });
        } catch (\Throwable $exception) {
            if ($snapshotPath !== null) {
                Storage::disk('public')->delete($snapshotPath);
            }
            throw $exception;
        }

        return $verification;
    }

    public function snapshotPath(ManualVerification $verification): ?string
    {
        $path = $verification->foto_tangkapan;

        return $this->isPrivateSnapshotPath($path)
            && Storage::disk('local')->exists($path)
            ? $path
            : null;
    }

    private function isPrivateSnapshotPath(string $path): bool
    {
        return preg_match('/\Acompetition\/manual-verifications\/[0-9]+\.jpg\z/', $path) === 1;
    }

    private function decodeSnapshot(mixed $image): string
    {
        $maxBytes = (int) config('competition.manual_verification.max_snapshot_bytes', 2097152);
        if (! is_string($image) || strlen($image) > (int) ceil($maxBytes * 1.5) + 32
            || ! preg_match('/\Adata:image\/jpeg;base64,([A-Za-z0-9+\/=]+)\z/', $image, $matches)) {
            throw ValidationException::withMessages(['image' => 'Foto tangkapan tidak valid atau melebihi batas ukuran.']);
        }

        $bytes = base64_decode($matches[1], true);
        if ($bytes === false || strlen($bytes) > $maxBytes) {
            throw ValidationException::withMessages(['image' => 'Foto tangkapan tidak valid atau melebihi batas ukuran.']);
        }

        $imageInfo = @getimagesizefromstring($bytes);
        if (! is_array($imageInfo)
            || ($imageInfo[2] ?? null) !== IMAGETYPE_JPEG
            || ($imageInfo[0] ?? 0) > 1920
            || ($imageInfo[1] ?? 0) > 1920) {
            throw ValidationException::withMessages(['image' => 'Foto tangkapan harus berupa JPEG yang valid dan berukuran maksimal 1920 x 1920 piksel.']);
        }

        return $bytes;
    }
}
