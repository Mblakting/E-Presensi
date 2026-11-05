<?php

use App\Competition\Http\ChallengeController;
use App\Competition\Http\LivenessScriptController;
use App\Competition\Http\ManualVerificationController;
use App\Competition\Http\ReviewQueueController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::middleware('auth:siswa')->group(function (): void {
        Route::post('/competition/liveness/challenge', [ChallengeController::class, 'issue'])
            ->middleware('throttle:10,1')
            ->name('competition.liveness.challenge');
        Route::get('/competition/liveness.js', LivenessScriptController::class)
            ->name('competition.liveness.script');
    });

    Route::middleware('auth:user')->prefix('competition/reviews')->name('competition.reviews.')->group(function (): void {
        Route::get('/', [ReviewQueueController::class, 'index'])->name('index');
        Route::post('/{id}/decision', [ReviewQueueController::class, 'decide'])->name('decide');
        Route::get('/manual/{id}/snapshot', [ManualVerificationController::class, 'snapshot'])->name('manual.snapshot');
        Route::post('/manual/{id}/decision', [ManualVerificationController::class, 'decide'])->name('manual.decide');
    });
});
