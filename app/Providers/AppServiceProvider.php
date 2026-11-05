<?php

namespace App\Providers;

use App\Competition\Console\Commands\CreateDemoAdmin;
use App\Competition\Console\Commands\EraseSyntheticCompetitionSubject;
use App\Competition\Console\Commands\ExportEvaluationSnapshot;
use App\Competition\Console\Commands\ImportEvaluationTrials;
use App\Competition\Console\Commands\PurgeLivenessData;
use App\Competition\Console\Commands\PurgeTrustScores;
use App\Competition\Events\AbsenRecorded;
use App\Competition\Http\CompetitionErrorMiddleware;
use App\Competition\Http\CompetitionNavigationMiddleware;
use App\Competition\Listeners\RecordTrustScore;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        $router = $this->app->make(Router::class);
        $this->configureCompetitionMiddleware($router);
        $this->app->afterResolving(HttpKernel::class, function () use ($router): void {
            $this->configureCompetitionMiddleware($router);
        });

        if (config('competition.consent_gate', false)) {
            $this->loadRoutesFrom(app_path('Competition/Routes/consent.php'));
        }

        if (config('competition.trust_engine_enforce', false)) {
            $this->loadRoutesFrom(app_path('Competition/Routes/web.php'));
        }

        if (config('competition.ai_evaluation_dashboard', false)) {
            $this->loadRoutesFrom(app_path('Competition/Routes/evaluation.php'));
        }

        if (config('competition.audit_viewer', false)) {
            $this->loadRoutesFrom(app_path('Competition/Routes/audit.php'));
        }

        if (config('competition.competition_nav', false)) {
            $this->loadRoutesFrom(app_path('Competition/Routes/navigation.php'));
        }

        $this->commands([
            CreateDemoAdmin::class,
            PurgeTrustScores::class,
            PurgeLivenessData::class,
            ImportEvaluationTrials::class,
            ExportEvaluationSnapshot::class,
            EraseSyntheticCompetitionSubject::class,
        ]);

        // [DIGIFEST 2026] Daftarkan event listener Trust Engine
        // Hanya aktif jika FEATURE_TRUST_ENGINE_SHADOW=true — tidak ada efek jika false
        if (config('competition.trust_engine_shadow', false)) {
            Event::listen(AbsenRecorded::class, RecordTrustScore::class);
        }

        // Suppress deprecated PDO::MYSQL_ATTR_SSL_CA warning di PHP 8.5+
        if (PHP_VERSION_ID >= 80500) {
            set_error_handler(function ($errno, $errstr) {
                if (str_contains($errstr, 'MYSQL_ATTR_SSL_CA')) {
                    return true;
                }

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

    private function configureCompetitionMiddleware(Router $router): void
    {
        $router->aliasMiddleware('auth', Authenticate::class);
        $router->aliasMiddleware('guest', RedirectIfAuthenticated::class);
        $router->aliasMiddleware('trust.engine', \App\Competition\Http\TrustEngineMiddleware::class);
        $router->aliasMiddleware('competition.enforce', \App\Competition\Http\EnforceMiddleware::class);

        if (config('competition.trust_engine_shadow', false)) {
            $this->pushMiddlewareToGroupOnce($router, 'web', \App\Competition\Http\TrustEngineMiddleware::class);
        }

        if (config('competition.consent_gate', false)) {
            $this->pushMiddlewareToGroupOnce($router, 'web', \App\Competition\Http\ConsentGateMiddleware::class);
        }

        if (config('competition.trust_engine_enforce', false)) {
            $this->pushMiddlewareToGroupOnce($router, 'web', \App\Competition\Http\EnforceMiddleware::class);
        }

        if (config('competition.competition_nav', false)) {
            $this->pushMiddlewareToGroupOnce($router, 'web', CompetitionErrorMiddleware::class);
            $this->pushMiddlewareToGroupOnce($router, 'web', CompetitionNavigationMiddleware::class);
        }
    }

    private function pushMiddlewareToGroupOnce(Router $router, string $group, string $middleware): void
    {
        if (! in_array($middleware, $router->getMiddlewareGroups()[$group] ?? [], true)) {
            $router->pushMiddlewareToGroup($group, $middleware);
        }
    }
}
