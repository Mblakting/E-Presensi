<?php

use App\Competition\Http\EvaluationController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::middleware('auth:user')->get('/competition/ai-evaluation', [EvaluationController::class, 'index'])
        ->name('competition.evaluation.index');
});
