<?php

namespace App\Competition\Http;

use App\Competition\Models\AuditLog;
use App\Competition\Models\ManualVerification;
use App\Competition\Models\VerificationQueueItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewQueueController
{
    public function index(): View
    {
        AuditLog::record(
            'verification_queue_accessed',
            'admin',
            (string) Auth::guard('user')->id(),
            null,
            ['page' => 'review_queue']
        );

        $items = VerificationQueueItem::query()
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(25);
        $manualItems = ManualVerification::query()
            ->join('siswa', 'siswa.nisn', '=', 'competition_manual_verifications.siswa_nisn')
            ->select('competition_manual_verifications.*', 'siswa.nama_lengkap')
            ->orderByRaw("CASE WHEN competition_manual_verifications.status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('competition_manual_verifications.attempted_at')
            ->paginate(25, ['*'], 'manual_page');

        return view('competition::reviews.index', compact('items', 'manualItems'));
    }

    public function decide(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,suspicious'],
        ]);

        DB::transaction(function () use ($id, $validated): void {
            $item = VerificationQueueItem::query()->lockForUpdate()->findOrFail($id);
            abort_unless($item->status === 'pending', 409, 'Item ini sudah ditinjau.');

            $item->update([
                'status' => $validated['decision'],
                'reviewer_id' => (string) Auth::guard('user')->id(),
                'reviewed_at' => now(),
            ]);

            AuditLog::record(
                'verification_review_decision',
                'admin',
                (string) Auth::guard('user')->id(),
                $item->siswa_nisn,
                ['queue_id' => $item->id, 'decision' => $validated['decision']],
                str_starts_with($item->siswa_nisn, '9999')
            );
        });

        return back()->with('status', 'Keputusan verifikasi berhasil disimpan.');
    }
}
