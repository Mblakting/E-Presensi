<?php

namespace App\Competition\Http;

use App\Competition\Services\LivenessChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChallengeController
{
    public function issue(Request $request, LivenessChallengeService $challenges): JsonResponse
    {
        $nisn = Auth::guard('siswa')->id();
        abort_unless(is_string($nisn) && $nisn !== '', 401);

        return response()->json($challenges->issue($nisn, $request->session()->getId()))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
