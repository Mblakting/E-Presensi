<?php

namespace App\Competition\Services;

use App\Competition\Models\ConsentRecord;

class ConsentService
{
    public function latest(string $nisn): ?ConsentRecord
    {
        return ConsentRecord::query()
            ->where('siswa_nisn', $nisn)
            ->orderByDesc('id')
            ->first();
    }

    public function isGranted(string $nisn): bool
    {
        if (! (bool) config('competition.consent.text_approved', false)) {
            return false;
        }

        $latest = $this->latest($nisn);

        return $latest?->status === 'granted'
            && $latest->text_version === (string) config('competition.consent.version')
            && $latest->text_hash === hash('sha256', (string) config('competition.consent.text'));
    }

    /**
     * @param  list<string>  $nisns
     * @return list<string>
     */
    public function grantedNisns(array $nisns): array
    {
        if ($nisns === [] || ! (bool) config('competition.consent.text_approved', false)) {
            return [];
        }

        $latestIds = ConsentRecord::query()
            ->selectRaw('MAX(id)')
            ->whereIn('siswa_nisn', $nisns)
            ->groupBy('siswa_nisn');

        return ConsentRecord::query()
            ->whereIn('id', $latestIds)
            ->where('status', 'granted')
            ->where('text_version', (string) config('competition.consent.version'))
            ->where('text_hash', hash('sha256', (string) config('competition.consent.text')))
            ->pluck('siswa_nisn')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function allGrantedNisns(): array
    {
        if (! (bool) config('competition.consent.text_approved', false)) {
            return [];
        }

        $latestIds = ConsentRecord::query()
            ->selectRaw('MAX(id)')
            ->groupBy('siswa_nisn');

        return ConsentRecord::query()
            ->whereIn('id', $latestIds)
            ->where('status', 'granted')
            ->where('text_version', (string) config('competition.consent.version'))
            ->where('text_hash', hash('sha256', (string) config('competition.consent.text')))
            ->pluck('siswa_nisn')
            ->all();
    }

    public function record(
        string $nisn,
        string $status,
        ?string $authorityBasis = null,
        string $actorType = 'admin',
        ?string $actorId = null
    ): ConsentRecord {
        $text = (string) config('competition.consent.text');

        return ConsentRecord::query()->create([
            'siswa_nisn' => $nisn,
            'status' => $status,
            'text_version' => (string) config('competition.consent.version'),
            'text_hash' => hash('sha256', $text),
            'actor_type' => $actorType,
            'actor_id' => $actorId ?? (string) auth('user')->id(),
            'authority_basis' => $authorityBasis,
            'recorded_at' => now(),
        ]);
    }
}
