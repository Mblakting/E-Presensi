<?php

namespace App\Competition\Http;

use App\Competition\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuditViewerController
{
    public function index(): View
    {
        AuditLog::record(
            'audit_viewer_accessed',
            'admin',
            (string) Auth::guard('user')->id(),
            null,
            ['page' => 'audit_viewer']
        );

        $events = AuditLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50);

        return view('competition::audit.index', compact('events'));
    }
}
