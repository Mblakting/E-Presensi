<?php

namespace App\Competition\Http;

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

    public function grant(Request $request, string $nisn, ConsentService $consents): RedirectResponse
    {
        if (! (bool) config('competition.consent.text_approved', false)) {
            return back()->with('warning', 'Draf persetujuan belum ditinjau dan disetujui; persetujuan belum dapat dicatat.');
        }

        $validated = $request->validate([
            'authority_basis' => ['required', 'in:adult_volunteer,school_guardian_record'],
            'authority_verified' => ['accepted'],
        ]);
        abort_unless(DB::table('siswa')->where('nisn', $nisn)->exists(), 404, 'Siswa tidak ditemukan.');

        $latest = $consents->latest($nisn);
        if ($latest?->status === 'granted'
            && $latest->text_version === (string) config('competition.consent.version')
            && $latest->text_hash === hash('sha256', (string) config('competition.consent.text'))) {
            return back()->with('status', 'Persetujuan masih aktif; tidak ada perubahan yang dicatat.');
        }

        $consents->record($nisn, 'granted', $validated['authority_basis']);

        return back()->with('status', 'Persetujuan dicatat. Pastikan proses tertulis yang sesuai telah diverifikasi.');
    }

    public function withdraw(string $nisn, ConsentService $consents): RedirectResponse
    {
        abort_unless(DB::table('siswa')->where('nisn', $nisn)->exists(), 404, 'Siswa tidak ditemukan.');

        $latest = $consents->latest($nisn);
        if ($latest?->status !== 'granted') {
            return back()->with('status', 'Tidak ada persetujuan aktif untuk ditarik.');
        }

        $consents->record($nisn, 'withdrawn');

        return back()->with('status', 'Persetujuan ditarik. Pemrosesan wajah kompetisi untuk NISN ini dihentikan.');
    }
}
