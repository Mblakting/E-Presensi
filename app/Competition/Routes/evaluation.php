<?php

use App\Competition\Http\EvaluationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

Route::middleware('web')->group(function (): void {
    View::addNamespace('competition', app_path('Competition/Resources/views'));

    Route::middleware('auth:user')->get('/competition/ai-evaluation', [EvaluationController::class, 'index'])
        ->name('competition.evaluation.index');
});
