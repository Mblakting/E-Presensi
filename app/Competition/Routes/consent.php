<?php

use App\Competition\Http\ConsentController;
use App\Competition\Http\StudentConsentController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

Route::middleware('web')->group(function (): void {
    View::addNamespace('competition', app_path('Competition/Resources/views'));

    Route::middleware('auth:user')->prefix('competition/consents')->name('competition.consents.')->group(function (): void {
        Route::get('/', [ConsentController::class, 'index'])->name('index');
        Route::post('/grant', [ConsentController::class, 'grant'])->name('grant');
        Route::post('/withdraw', [ConsentController::class, 'withdraw'])->name('withdraw');
    });

    Route::middleware('auth:siswa')->prefix('competition/consent')->name('competition.student-consents.')->group(function (): void {
        Route::get('/', [StudentConsentController::class, 'index'])->name('index');
        Route::post('/grant', [StudentConsentController::class, 'grant'])->name('grant');
        Route::post('/withdraw', [StudentConsentController::class, 'withdraw'])->name('withdraw');
    });
});
