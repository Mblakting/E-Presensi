<?php

use App\Competition\Http\CompetitionNavigationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

Route::middleware('web')->group(function (): void {
    View::addNamespace('competition', app_path('Competition/Resources/views'));

    Route::get('/competition', [CompetitionNavigationController::class, 'index'])
        ->name('competition.home');
    Route::get('/competition/guide', [CompetitionNavigationController::class, 'guide'])
        ->name('competition.guide');

    Route::get('/competition/{path?}', static fn () => abort(404))
        ->where('path', '.*')
        ->name('competition.not-found');
});
