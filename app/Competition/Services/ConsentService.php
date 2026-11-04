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
        if ($nisns === []) {
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

    public function record(
        string $nisn,
        string $status,
        ?string $authorityBasis = null
    ): ConsentRecord {
        $text = (string) config('competition.consent.text');

        return ConsentRecord::query()->create([
            'siswa_nisn' => $nisn,
            'status' => $status,
            'text_version' => (string) config('competition.consent.version'),
            'text_hash' => hash('sha256', $text),
            'actor_type' => 'admin',
            'actor_id' => (string) auth('user')->id(),
            'authority_basis' => $authorityBasis,
            'recorded_at' => now(),
        ]);
    }
}
