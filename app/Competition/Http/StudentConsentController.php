<?php

namespace App\Competition\Http;

use App\Competition\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StudentConsentController
{
    public function index(ConsentService $consents): View
    {
        $nisn = (string) Auth::guard('siswa')->id();
        $latest = Schema::hasTable('competition_consents')
            ? $consents->latest($nisn)
            : null;

        return view('competition::consents.student', [
            'consentText' => (string) config('competition.consent.text'),
            'textApproved' => (bool) config('competition.consent.text_approved', false),
            'version' => (string) config('competition.consent.version'),
            'consentStatus' => match (true) {
                $latest === null => 'Belum ada persetujuan',
                $consents->isGranted($nisn) => 'Persetujuan aktif',
                $latest->status === 'withdrawn' => 'Persetujuan ditarik',
                default => 'Persetujuan perlu diperbarui',
            },
            'latestStatus' => $latest?->status,
        ]);
    }

    public function grant(Request $request, ConsentService $consents): RedirectResponse
    {
        if (! (bool) config('competition.consent.text_approved', false)) {
            return back()->with('warning', 'Teks persetujuan masih draf. Persetujuan belum dapat dicatat.');
        }

        $request->validate([
            'adult_volunteer_verified' => ['accepted'],
        ]);

        $nisn = (string) Auth::guard('siswa')->id();
        if ($consents->isGranted($nisn)) {
            return back()->with('status', 'Persetujuan Anda untuk versi teks ini masih aktif.');
        }

        $consents->record($nisn, 'granted', 'adult_volunteer', 'student', $nisn);

        return back()->with('status', 'Persetujuan Anda dicatat. Anda dapat kembali ke presensi wajah.');
    }

    public function withdraw(ConsentService $consents): RedirectResponse
    {
        $nisn = (string) Auth::guard('siswa')->id();
        if (! $consents->isGranted($nisn)) {
            return back()->with('status', 'Tidak ada persetujuan aktif untuk ditarik.');
        }

        $consents->record($nisn, 'withdrawn', null, 'student', $nisn);

        return back()->with('status', 'Persetujuan ditarik. Pemrosesan wajah kompetisi untuk akun Anda dihentikan.');
    }
}
