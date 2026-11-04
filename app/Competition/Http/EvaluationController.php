<?php

namespace App\Competition\Http;

use App\Competition\Services\EvaluationReportService;
use Illuminate\View\View;

class EvaluationController
{
    public function index(EvaluationReportService $report): View
    {
        return view('competition::evaluation.index', $report->report());
    }
}
