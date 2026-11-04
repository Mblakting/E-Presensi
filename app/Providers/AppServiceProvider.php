<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use App\Competition\Events\AbsenRecorded;
use App\Competition\Listeners\RecordTrustScore;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // [DIGIFEST 2026] Daftarkan event listener Trust Engine
        // Hanya aktif jika FEATURE_TRUST_ENGINE_SHADOW=true — tidak ada efek jika false
        if (config('competition.trust_engine_shadow', false)) {
            Event::listen(AbsenRecorded::class, RecordTrustScore::class);
        }

        // Suppress deprecated PDO::MYSQL_ATTR_SSL_CA warning di PHP 8.5+
        if (PHP_VERSION_ID >= 80500) {
            set_error_handler(function ($errno, $errstr) {
                if (str_contains($errstr, 'MYSQL_ATTR_SSL_CA')) return true;
                return false;
            }, E_DEPRECATED);
        }

        // Set locale Carbon ke Bahasa Indonesia
        Carbon::setLocale(config('app.locale', 'id'));

        // Force HTTPS di production
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
