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
        bool $useConfigCache
    ): void {
        $cachePath = $useConfigCache
            ? 'bootstrap/cache/config-test-'.bin2hex(random_bytes(8)).'.php'
            : null;
        $absoluteCachePath = $cachePath === null ? null : base_path($cachePath);
        $environment = $this->environment($featuresEnabled, $cachePath);

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
                $this->assertFileExists($absoluteCachePath);
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
            }
        } finally {
            if ($absoluteCachePath !== null && file_exists($absoluteCachePath)) {
                unlink($absoluteCachePath);
            }
        }
    }

    public static function bootstrapModes(): array
    {
        return [
            'all competition flags off' => [false, false],
            'all competition flags on' => [true, false],
            'all competition flags on with config cache' => [true, true],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function environment(bool $featuresEnabled, ?string $configCachePath): array
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
        ];
        if ($configCachePath !== null) {
            $environment['APP_CONFIG_CACHE'] = $configCachePath;
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
                'web_middleware' => $app['router']->getMiddlewareGroups()['web'] ?? [],
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
