<?php

namespace App\Competition\Http;

use App\Competition\Models\AuditLog;
use App\Competition\Models\ConsentRecord;
use App\Competition\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConsentController
{
    public function index(ConsentService $consents): View
    {
        $latestIds = ConsentRecord::query()
            ->selectRaw('MAX(id)')
            ->groupBy('siswa_nisn');
        $records = ConsentRecord::query()
            ->whereIn('id', $latestIds)
            ->orderByDesc('id')
            ->paginate(25);
        $approved = (bool) config('competition.consent.text_approved', false);
        $text = (string) config('competition.consent.text');

        return view('competition::consents.index', [
            'records' => $records,
            'text' => $text,
            'textHash' => hash('sha256', $text),
            'textApproved' => $approved,
            'version' => config('competition.consent.version'),
        ]);
    }

    public function grant(Request $request, ConsentService $consents): RedirectResponse
    {
        if (! (bool) config('competition.consent.text_approved', false)) {
            return back()->with('warning', 'Draf persetujuan belum ditinjau dan disetujui; persetujuan belum dapat dicatat.');
        }

        $validated = $request->validate([
            'nisn' => ['required', 'string', 'exists:siswa,nisn'],
            'authority_basis' => ['required', 'in:adult_volunteer,school_guardian_record'],
            'authority_verified' => ['accepted'],
        ]);
        $nisn = $validated['nisn'];

        $latest = $consents->latest($nisn);
        if ($latest?->status === 'granted'
            && $latest->text_version === (string) config('competition.consent.version')
            && $latest->text_hash === hash('sha256', (string) config('competition.consent.text'))) {
            return back()->with('status', 'Persetujuan masih aktif; tidak ada perubahan yang dicatat.');
        }

        DB::transaction(function () use ($consents, $nisn, $validated): void {
            $record = $consents->record($nisn, 'granted', $validated['authority_basis']);
            AuditLog::record(
                'consent_granted',
                'admin',
                (string) auth('user')->id(),
                $nisn,
                ['consent_id' => $record->id, 'version' => $record->text_version, 'authority_basis' => $record->authority_basis],
                str_starts_with($nisn, '9999')
            );
        });

        return back()->with('status', 'Persetujuan dicatat. Pastikan proses tertulis yang sesuai telah diverifikasi.');
    }

    public function withdraw(Request $request, ConsentService $consents): RedirectResponse
    {
        $validated = $request->validate([
            'nisn' => ['required', 'string', 'exists:siswa,nisn'],
        ]);
        $nisn = $validated['nisn'];

        $latest = $consents->latest($nisn);
        if ($latest?->status !== 'granted') {
            return back()->with('status', 'Tidak ada persetujuan aktif untuk ditarik.');
        }

        DB::transaction(function () use ($consents, $nisn): void {
            $record = $consents->record($nisn, 'withdrawn');
            AuditLog::record(
                'consent_withdrawn',
                'admin',
                (string) auth('user')->id(),
                $nisn,
                ['consent_id' => $record->id, 'version' => $record->text_version],
                str_starts_with($nisn, '9999')
            );
        });

        return back()->with('status', 'Persetujuan ditarik. Pemrosesan wajah kompetisi untuk NISN ini dihentikan.');
    }
}
