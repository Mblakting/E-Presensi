<?php

namespace App\Competition\Http;

use App\Competition\Models\AuditLog;
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

        return view('competition::reviews.index', compact('items'));
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
