<?php

namespace App\Competition\Http;

use App\Competition\Models\AuditLog;
use App\Competition\Services\EvaluationReportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EvaluationController
{
    public function index(EvaluationReportService $report): View
    {
        AuditLog::record(
            'evaluation_dashboard_accessed',
            'admin',
            (string) Auth::guard('user')->id(),
            null,
            ['page' => 'ai_evaluation']
        );

        return view('competition::evaluation.index', $report->report());
    }
}
