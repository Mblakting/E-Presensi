<?php

namespace App\Competition\Http;

use App\Competition\Models\AuditLog;
use App\Competition\Models\ManualVerification;
use App\Competition\Services\ManualVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualVerificationController
{
    public function snapshot(int $id, ManualVerificationService $verifications): StreamedResponse
    {
        $item = ManualVerification::query()->findOrFail($id);
        $path = $verifications->snapshotPath($item);
        abort_if($path === null, 404);

        AuditLog::record(
            'manual_verification_snapshot_accessed',
            'admin',
            (string) Auth::guard('user')->id(),
            $item->siswa_nisn,
            ['verification_id' => $item->id],
            str_starts_with($item->siswa_nisn, '9999')
        );

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function decide(Request $request, int $id, ManualVerificationService $verifications): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $verifications->decide(
            $id,
            $validated['decision'],
            (string) Auth::guard('user')->id(),
            $validated['catatan'] ?? null
        );

        return back()->with('status', 'Keputusan verifikasi manual berhasil disimpan.');
    }
}
