<?php

use App\Competition\Http\AuditViewerController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

Route::middleware('web')->group(function (): void {
    View::addNamespace('competition', app_path('Competition/Resources/views'));

    Route::middleware('auth:user')->get('/competition/audit', [AuditViewerController::class, 'index'])
        ->name('competition.audit.index');
});
