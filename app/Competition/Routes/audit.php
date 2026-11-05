<?php

use App\Competition\Http\AuditViewerController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::middleware('auth:user')->get('/competition/audit', [AuditViewerController::class, 'index'])
        ->name('competition.audit.index');
});
