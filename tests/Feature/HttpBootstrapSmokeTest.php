<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class HttpBootstrapSmokeTest extends TestCase
{
    #[DataProvider('bootstrapModes')]
    public function test_fresh_http_kernel_bootstrap_handles_real_requests_without_server_error(
        bool $featuresEnabled,
        bool $navigationEnabled,
        bool $useConfigCache,
        bool $useRouteCache
    ): void {
        $configCachePath = $useConfigCache
            ? 'bootstrap/cache/config-test-'.bin2hex(random_bytes(8)).'.php'
            : null;
        $routeCachePath = $useRouteCache
            ? 'bootstrap/cache/routes-test-'.bin2hex(random_bytes(8)).'.php'
            : null;
        $absoluteConfigCachePath = $configCachePath === null ? null : base_path($configCachePath);
        $absoluteRouteCachePath = $routeCachePath === null ? null : base_path($routeCachePath);
        $environment = $this->environment($featuresEnabled, $navigationEnabled, $configCachePath, $routeCachePath);

        try {
            if ($useConfigCache) {
                $cacheProcess = new Process(
                    [PHP_BINARY, 'artisan', 'config:cache'],
                    base_path(),
                    $environment,
                    timeout: 60
                );
                $cacheProcess->run();

                $this->assertSame(
                    0,
                    $cacheProcess->getExitCode(),
                    $cacheProcess->getErrorOutput().$cacheProcess->getOutput()
                );
                $this->assertFileExists($absoluteConfigCachePath);
            }

            if ($useRouteCache) {
                $cacheProcess = new Process(
                    [PHP_BINARY, 'artisan', 'route:cache'],
                    base_path(),
                    $environment,
                    timeout: 60
                );
                $cacheProcess->run();

                $this->assertSame(
                    0,
                    $cacheProcess->getExitCode(),
                    $cacheProcess->getErrorOutput().$cacheProcess->getOutput()
                );
                $this->assertFileExists($absoluteRouteCachePath);
            }

            foreach (['/login', '/', '/presensi/face'] as $uri) {
                $result = $this->handleInFreshProcess($uri, $environment);

                $this->assertGreaterThanOrEqual(200, $result['status'], $uri);
                $this->assertLessThan(500, $result['status'], $uri);

                if ($featuresEnabled) {
                    $this->assertTrue($result['has_liveness_route']);
                    $this->assertTrue($result['has_consent_route']);
                    $this->assertTrue($result['has_evaluation_route']);
                    $this->assertTrue($result['has_audit_route']);
                    $this->assertContains(
                        \App\Competition\Http\EnforceMiddleware::class,
                        $result['web_middleware']
                    );
                } else {
                    $this->assertFalse($result['has_liveness_route']);
                    $this->assertFalse($result['has_consent_route']);
                    $this->assertFalse($result['has_evaluation_route']);
                    $this->assertFalse($result['has_audit_route']);
                    $this->assertNotContains(
                        \App\Competition\Http\EnforceMiddleware::class,
                        $result['web_middleware']
                    );
                }

                $this->assertSame($navigationEnabled, $result['has_competition_hub']);
                $this->assertSame($navigationEnabled, $result['has_competition_guide']);
                $this->assertSame($navigationEnabled, $result['has_navigation_middleware']);
                $this->assertSame(
                    $featuresEnabled && $navigationEnabled,
                    $result['has_student_consent_route']
                );
            }
        } finally {
            foreach ([$absoluteConfigCachePath, $absoluteRouteCachePath] as $cacheFile) {
                if ($cacheFile !== null && file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
            }
        }
    }

    public static function bootstrapModes(): array
    {
        return [
            'all competition flags off' => [false, false, false, false],
            'navigation only' => [false, true, false, false],
            'all features and navigation on' => [true, true, false, false],
            'all features with config cache' => [true, true, true, false],
            'all features with route cache' => [true, true, false, true],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function environment(
        bool $featuresEnabled,
        bool $navigationEnabled,
        ?string $configCachePath,
        ?string $routeCachePath
    ): array
    {
        $enabled = $featuresEnabled ? 'true' : 'false';

        $environment = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'FEATURE_TRUST_ENGINE_SHADOW' => $enabled,
            'FEATURE_TRUST_ENGINE_ENFORCE' => $enabled,
            'FEATURE_COMPETITION_CONSENT' => $enabled,
            'FEATURE_AI_EVALUATION_DASHBOARD' => $enabled,
            'FEATURE_COMPETITION_AUDIT_VIEWER' => $enabled,
            'FEATURE_COMPETITION_DATA_ERASURE' => $enabled,
            'FEATURE_COMPETITION_NAV' => $navigationEnabled ? 'true' : 'false',
        ];
        if ($configCachePath !== null) {
            $environment['APP_CONFIG_CACHE'] = $configCachePath;
        }
        if ($routeCachePath !== null) {
            $environment['APP_ROUTES_CACHE'] = $routeCachePath;
        }

        return $environment;
    }

    /**
     * @param  array<string, string>  $environment
     * @return array{
     *     status: int,
     *     has_liveness_route: bool,
     *     has_consent_route: bool,
     *     has_evaluation_route: bool,
     *     has_audit_route: bool,
     *     has_competition_hub: bool,
     *     has_competition_guide: bool,
     *     has_student_consent_route: bool,
     *     has_navigation_middleware: bool,
     *     web_middleware: list<string>
     * }
     */
    private function handleInFreshProcess(string $uri, array $environment): array
    {
        $uriLiteral = var_export($uri, true);
        $code = <<<'PHP'
            require getcwd().'/vendor/autoload.php';
            $app = require getcwd().'/bootstrap/app.php';
            $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
            $request = \Illuminate\Http\Request::create(%s, 'GET');
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
            $routes = $app['router']->getRoutes();
            echo json_encode([
                'status' => $response->getStatusCode(),
                'has_liveness_route' => $routes->hasNamedRoute('competition.liveness.script'),
                'has_consent_route' => $routes->hasNamedRoute('competition.consents.index'),
                'has_evaluation_route' => $routes->hasNamedRoute('competition.evaluation.index'),
                'has_audit_route' => $routes->hasNamedRoute('competition.audit.index'),
                'has_competition_hub' => $routes->hasNamedRoute('competition.home'),
                'has_competition_guide' => $routes->hasNamedRoute('competition.guide'),
                'has_student_consent_route' => $routes->hasNamedRoute('competition.student-consents.index'),
                'web_middleware' => $app['router']->getMiddlewareGroups()['web'] ?? [],
                'has_navigation_middleware' => in_array(
                    \App\Competition\Http\CompetitionNavigationMiddleware::class,
                    $app['router']->getMiddlewareGroups()['web'] ?? [],
                    true
                ),
            ], JSON_THROW_ON_ERROR);
            PHP;
        $code = sprintf($code, $uriLiteral);

        $process = new Process([PHP_BINARY, '-r', $code], base_path(), $environment, timeout: 60);
        $process->run();

        $this->assertSame(
            0,
            $process->getExitCode(),
            $process->getErrorOutput().$process->getOutput()
        );

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($result);

        return $result;
    }
}
