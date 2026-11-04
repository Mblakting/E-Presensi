<?php

namespace Tests\Feature;

use App\Competition\Events\AbsenRecorded;
use App\Competition\Http\TrustEngineMiddleware;
use App\Competition\Models\TrustScore;
use App\Competition\Services\TrustEngineService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TrustEngineMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('database/migrations/2026_digifest_001_create_competition_trust_scores_table.php');
        $migration->up();
        config(['competition.trust_engine_shadow' => false]);
    }

    public function test_disabled_flag_does_not_dispatch_or_query_trust_scores(): void
    {
        $dispatched = false;
        $trustQueries = 0;
        $this->app['events']->listen(AbsenRecorded::class, function () use (&$dispatched): void {
            $dispatched = true;
        });
        DB::listen(function ($query) use (&$trustQueries): void {
            if (str_contains($query->sql, 'competition_trust_scores')) {
                $trustQueries++;
            }
        });

        $request = $this->successfulFaceAttendanceRequest();
        $response = new JsonResponse(['status' => 'success', 'type' => 'in']);
        $middleware = new TrustEngineMiddleware();

        $handledResponse = $middleware->handle($request, fn () => $response);
        $middleware->terminate($request, $handledResponse);

        $this->assertSame($response, $handledResponse);
        $this->assertFalse($dispatched);
        $this->assertSame(0, $trustQueries);
        $this->assertSame(0, TrustScore::query()->count());
    }

    public function test_response_is_successful_before_terminable_listener_errors(): void
    {
        config(['competition.trust_engine_shadow' => true]);
        $listenerCalled = false;
        $this->app['events']->listen(AbsenRecorded::class, function (AbsenRecorded $event) use (&$listenerCalled): void {
            $listenerCalled = true;
            app(TrustEngineService::class)->evaluate(
                nisn: $event->nisn,
                absenType: $event->absenType,
                method: $event->method,
                faceDistance: $event->faceDistance,
                lokasi: $event->lokasi,
                tglPresensi: $event->tglPresensi,
                jamAbsen: $event->jamAbsen,
                gpsAccuracy: $event->gpsAccuracy,
            );
        });
        $serviceCalled = false;
        $engine = Mockery::mock(TrustEngineService::class);
        $engine->shouldReceive('evaluate')->once()->andReturnUsing(function () use (&$serviceCalled): never {
            $serviceCalled = true;
            throw new RuntimeException('simulated service failure');
        });
        $this->app->instance(TrustEngineService::class, $engine);
        Log::spy();

        $request = $this->successfulFaceAttendanceRequest();
        $response = new JsonResponse(['status' => 'success', 'type' => 'in']);
        $middleware = new TrustEngineMiddleware();

        $handledResponse = $middleware->handle($request, fn () => $response);
        $this->assertArrayHasKey('competition.trust_engine_event', $request->attributes->all());
        $this->assertNotEmpty($this->app['events']->getListeners(AbsenRecorded::class));

        $this->assertSame(200, $handledResponse->getStatusCode());
        $this->assertSame('success', $handledResponse->getData(true)['status']);
        $this->assertFalse($serviceCalled);

        $middleware->terminate($request, $handledResponse);

        $this->assertTrue($listenerCalled);
        $this->assertTrue($serviceCalled);
        $this->assertSame(200, $handledResponse->getStatusCode());
        $this->assertSame('success', $handledResponse->getData(true)['status']);
    }

    private function successfulFaceAttendanceRequest(): Request
    {
        $request = Request::create('/presensi/face/store', 'POST', [
            'nisn_match' => '9999000001',
            'confidence' => 0.2,
            'lokasi' => '-7.671325,110.589607',
            'accuracy' => 5,
        ]);
        $request->setRouteResolver(fn () => (new Route('POST', '/presensi/face/store', []))->name('presensi.face.store'));

        return $request;
    }
}
