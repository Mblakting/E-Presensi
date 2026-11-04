<?php

namespace Tests\Feature;

use App\Competition\Http\EnforceMiddleware;
use App\Competition\Models\LivenessChallenge;
use App\Competition\Services\LivenessChallengeService;
use App\Competition\Services\TrustDecisionService;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CompetitionEnforceMiddlewareTest extends TestCase
{
    private const NISN = '9999000001';

    private const LOCATION = '-7.671325,110.589607';

    private const SESSION_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'competition.trust_engine_enforce' => true,
            'competition.liveness_challenge.ttl_seconds' => 90,
            'competition.liveness_challenge.thresholds.unavailable_daily_limit' => 2,
            'school.latitude' => -7.671325,
            'school.longitude' => 110.589607,
            'school.radius_meters' => 100,
        ]);
        Carbon::setTestNow('2026-11-04 07:15:00');

        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
            $table->string('password');
            $table->timestamps();
        });
        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('nisn');
            $table->date('tgl_presensi');
            $table->time('jam_in')->nullable();
            $table->time('jam_out')->nullable();
            $table->unique(['nisn', 'tgl_presensi']);
        });
        Schema::create('school_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->timestamps();
        });
        Schema::create('competition_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 50);
            $table->string('actor_type', 20);
            $table->string('actor_id')->nullable();
            $table->string('subject_nisn')->nullable();
            $table->text('payload_hash')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_synthetic')->default(false);
            $table->timestamps();
        });
        DB::table('school_settings')->insert([
            'latitude' => -7.671325,
            'longitude' => 110.589607,
            'radius_meters' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('siswa')->insert([
            'nisn' => self::NISN,
            'password' => 'test-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runCompetitionMigrations();
        require app_path('Competition/Routes/web.php');
        \Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();
        \Illuminate\Support\Facades\Route::getRoutes()->refreshActionLookups();
        Auth::guard('siswa')->setUser(Siswa::query()->findOrFail(self::NISN));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_missing_challenge_is_rejected_before_controller(): void
    {
        $called = false;
        $response = $this->middleware()->handle(
            $this->attendanceRequest([]),
            function () use (&$called): JsonResponse {
                $called = true;

                return response()->json(['status' => 'success']);
            }
        );

        $this->assertFalse($called);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('challenge_unavailable', $response->getData(true)['type']);
        $this->assertStringContainsString('Challenge keamanan', $response->getData(true)['message']);
    }

    public function test_valid_passed_challenge_reaches_controller_and_is_consumed_once(): void
    {
        $challenge = $this->issueChallenge();
        $calls = 0;
        $next = function () use (&$calls): JsonResponse {
            $calls++;

            return response()->json(['status' => 'success', 'type' => 'in', 'message' => 'legacy']);
        };

        $first = $this->middleware()->handle($this->attendanceRequest($this->passedPayload($challenge)), $next);
        $second = $this->middleware()->handle($this->attendanceRequest($this->passedPayload($challenge)), $next);

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(409, $second->getStatusCode());
        $this->assertSame('challenge_replayed', $second->getData(true)['type']);
        $this->assertSame(1, $calls);
        $this->assertDatabaseHas('competition_liveness_challenges', [
            'nonce_hash' => hash('sha256', $challenge['nonce']),
            'verdict' => 'accepted',
        ]);
    }

    public function test_expired_challenge_is_rejected_before_controller(): void
    {
        $challenge = $this->issueChallenge();
        LivenessChallenge::query()
            ->where('nonce_hash', hash('sha256', $challenge['nonce']))
            ->update(['expires_at' => now()->subSecond()]);
        $called = false;

        $response = $this->middleware()->handle(
            $this->attendanceRequest($this->passedPayload($challenge)),
            function () use (&$called): JsonResponse {
                $called = true;

                return response()->json(['status' => 'success']);
            }
        );

        $this->assertFalse($called);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('challenge_expired', $response->getData(true)['type']);
    }

    public function test_challenge_for_another_student_or_session_is_rejected_before_controller(): void
    {
        $challenge = $this->issueChallenge();
        DB::table('siswa')->insert([
            'nisn' => '9999000002',
            'password' => 'test-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Auth::guard('siswa')->setUser(Siswa::query()->findOrFail('9999000002'));
        $called = false;
        $wrongStudent = $this->middleware()->handle(
            $this->attendanceRequest($this->passedPayload($challenge)),
            function () use (&$called): JsonResponse {
                $called = true;

                return response()->json(['status' => 'success']);
            }
        );
        Auth::guard('siswa')->setUser(Siswa::query()->findOrFail(self::NISN));
        $wrongSession = $this->middleware()->handle(
            $this->attendanceRequest($this->passedPayload($challenge), str_repeat('b', 40)),
            function () use (&$called): JsonResponse {
                $called = true;

                return response()->json(['status' => 'success']);
            }
        );

        $this->assertSame(403, $wrongStudent->getStatusCode());
        $this->assertSame('challenge_owner_mismatch', $wrongStudent->getData(true)['type']);
        $this->assertSame(403, $wrongSession->getStatusCode());
        $this->assertSame('challenge_owner_mismatch', $wrongSession->getData(true)['type']);
        $this->assertFalse($called);
    }

    public function test_liveness_failure_rejects_even_when_other_signals_score_high(): void
    {
        $challenge = $this->issueChallenge();
        $payload = $this->passedPayload($challenge);
        $payload['liveness_status'] = 'failed';
        $called = false;

        $response = $this->middleware()->handle(
            $this->attendanceRequest($payload),
            function () use (&$called): JsonResponse {
                $called = true;

                return response()->json(['status' => 'success']);
            }
        );

        $this->assertFalse($called);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('liveness_failed', $response->getData(true)['type']);
        $this->assertStringContainsString('liveness', strtolower($response->getData(true)['message']));
    }

    public function test_review_verdict_is_held_without_calling_attendance_controller(): void
    {
        $successfulChallenge = $this->issueChallenge();
        $called = false;
        $successfulResponse = $this->middleware()->handle(
            $this->attendanceRequest($this->unavailablePayload($successfulChallenge)),
            function () use (&$called): JsonResponse {
                $called = true;

                return response()->json(['status' => 'success', 'type' => 'in', 'message' => 'legacy']);
            }
        );

        $this->assertFalse($called);
        $this->assertSame('success', $successfulResponse->getData(true)['status']);
        $this->assertSame('pending', $successfulResponse->getData(true)['verification_status']);
        $this->assertSame('Presensi memerlukan verifikasi manual admin. Mohon tunggu.', $successfulResponse->getData(true)['message']);
        $this->assertDatabaseCount('presensi', 0);
        $this->assertDatabaseCount('competition_verification_queue', 0);
        $this->assertDatabaseHas('competition_manual_verifications', [
            'challenge_id' => LivenessChallenge::query()
                ->where('nonce_hash', hash('sha256', $successfulChallenge['nonce']))
                ->value('id'),
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('competition_audit_logs', ['event_type' => 'manual_verification_queued']);
    }

    public function test_unavailable_liveness_fallback_is_capped_per_student_per_day(): void
    {
        config(['competition.liveness_challenge.thresholds.unavailable_daily_limit' => 1]);
        $first = $this->issueChallenge();
        $calls = 0;
        $this->middleware()->handle(
            $this->attendanceRequest($this->unavailablePayload($first)),
            function () use (&$calls): JsonResponse {
                $calls++;

                return response()->json(['status' => 'success', 'type' => 'in']);
            }
        );

        $second = $this->issueChallenge();
        $response = $this->middleware()->handle(
            $this->attendanceRequest($this->unavailablePayload($second)),
            function () use (&$calls): JsonResponse {
                $calls++;

                return response()->json(['status' => 'success', 'type' => 'out']);
            }
        );

        $this->assertSame(0, $calls);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('liveness_unavailable_daily_limit', $response->getData(true)['type']);
    }

    public function test_decision_service_failure_returns_explained_response_without_calling_controller(): void
    {
        $challenge = $this->issueChallenge();
        $service = Mockery::mock(TrustDecisionService::class);
        $service->shouldReceive('evaluate')->once()->andThrow(new RuntimeException('simulated evaluator failure'));
        $this->app->instance(TrustDecisionService::class, $service);
        Log::spy();
        $called = false;

        $response = $this->middleware()->handle(
            $this->attendanceRequest($this->passedPayload($challenge)),
            function () use (&$called): JsonResponse {
                $called = true;

                return response()->json(['status' => 'success']);
            }
        );

        $this->assertFalse($called);
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('evaluation_unavailable', $response->getData(true)['type']);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_attendance_page_response_gets_script_and_no_store_headers(): void
    {
        $request = Request::create('/presensi/face', 'GET');
        $request->setRouteResolver(fn () => (new Route('GET', '/presensi/face', []))->name('presensi.face'));
        $response = $this->middleware()->handle(
            $request,
            fn () => response('<html><body>attendance page</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8'])
        );

        $this->assertStringContainsString(route('competition.liveness.script'), $response->getContent());
        $this->assertLessThan(
            strpos($response->getContent(), '</body>'),
            strpos($response->getContent(), '<script defer')
        );
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_disabled_flag_does_not_inject_script_or_query_competition_tables(): void
    {
        config(['competition.trust_engine_enforce' => false]);
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'competition_')) {
                $queries++;
            }
        });
        $request = Request::create('/presensi/face', 'GET');
        $request->setRouteResolver(fn () => (new Route('GET', '/presensi/face', []))->name('presensi.face'));

        $response = $this->middleware()->handle(
            $request,
            fn () => response('<html><body>attendance page</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8'])
        );

        $this->assertStringNotContainsString('competition/liveness.js', $response->getContent());
        $this->assertSame(0, $queries);
    }

    private function runCompetitionMigrations(): void
    {
        foreach ([
            '2026_digifest_001_create_competition_trust_scores_table.php',
            '2026_digifest_002_create_competition_audit_logs_table.php',
            '2026_digifest_003_create_competition_liveness_challenges_table.php',
            '2026_digifest_004_create_competition_verification_queue_table.php',
            '2026_digifest_007_create_competition_manual_verifications_table.php',
        ] as $migrationFile) {
            $migration = require base_path('database/migrations/'.$migrationFile);
            $migration->up();
        }
    }

    private function middleware(): EnforceMiddleware
    {
        return new EnforceMiddleware;
    }

    private function issueChallenge(string $sessionId = self::SESSION_ID): array
    {
        return app(LivenessChallengeService::class)->issue(self::NISN, $sessionId);
    }

    private function passedPayload(array $challenge): array
    {
        return array_merge($this->basePayload(), [
            'challenge_nonce' => $challenge['nonce'],
            'liveness_status' => 'passed',
            'liveness_evidence' => [
                'delta' => match ($challenge['instruction']) {
                    'turn_left' => -0.2,
                    default => 0.2,
                },
                'elapsed_ms' => 1200,
            ],
        ]);
    }

    private function unavailablePayload(array $challenge): array
    {
        return array_merge($this->basePayload(), [
            'challenge_nonce' => $challenge['nonce'],
            'liveness_status' => 'unavailable',
            'liveness_evidence' => [],
        ]);
    }

    private function basePayload(): array
    {
        return [
            'nisn_match' => self::NISN,
            'confidence' => 0.2,
            'lokasi' => self::LOCATION,
            'image' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAAAAAAAAAAAAAAAAAAAAAf/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/AN+AD//Z',
        ];
    }

    private function attendanceRequest(array $payload, string $sessionId = self::SESSION_ID): Request
    {
        $request = Request::create('/presensi/face/store', 'POST', $payload);
        $request->setLaravelSession($this->createSession($sessionId));
        $request->setRouteResolver(fn () => (new Route('POST', '/presensi/face/store', []))->name('presensi.face.store'));

        return $request;
    }

    private function createSession(string $id): Store
    {
        $session = new Store('competition-test', new ArraySessionHandler(120));
        $session->setId($id);
        $session->start();

        return $session;
    }
}
