<?php

namespace Tests\Feature;

use App\Competition\Http\EnforceMiddleware;
use App\Competition\Models\LivenessChallenge;
use App\Competition\Services\LivenessChallengeService;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualVerificationQueueTest extends TestCase
{
    private const NISN = '9999000001';

    private const SESSION_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const JPEG = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAAAAAAAAAAAAAAAAAAAAAf/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/AN+AD//Z';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 07:15:00');
        config([
            'competition.trust_engine_enforce' => true,
            'competition.consent_gate' => false,
            'competition.liveness_challenge.ttl_seconds' => 90,
            'competition.liveness_challenge.weights' => [
                'face' => 0,
                'gps' => 0,
                'consistency' => 0,
                'liveness' => 50,
            ],
            'competition.liveness_challenge.thresholds.accept' => 71,
            'competition.liveness_challenge.thresholds.review' => 40,
            'school.latitude' => -7.671325,
            'school.longitude' => 110.589607,
            'school.radius_meters' => 100,
            'filesystems.disks.local.root' => storage_path('framework/testing/disks/manual-verifications'),
        ]);
        Storage::fake('local');
        Storage::fake('public');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
            $table->string('nama_lengkap');
            $table->string('kelas');
            $table->string('foto')->nullable();
            $table->text('face_descriptor')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('nisn');
            $table->date('tgl_presensi');
            $table->time('jam_in')->nullable();
            $table->string('foto_in')->nullable();
            $table->string('lokasi_in')->nullable();
            $table->time('jam_out')->nullable();
            $table->string('foto_out')->nullable();
            $table->string('lokasi_out')->nullable();
            $table->timestamps();
            $table->unique(['nisn', 'tgl_presensi']);
        });
        Schema::create('school_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->timestamps();
        });

        foreach ([
            '2026_digifest_001_create_competition_trust_scores_table.php',
            '2026_digifest_002_create_competition_audit_logs_table.php',
            '2026_digifest_003_create_competition_liveness_challenges_table.php',
            '2026_digifest_004_create_competition_verification_queue_table.php',
            '2026_digifest_005_create_competition_consents_table.php',
            '2026_digifest_006_create_competition_evaluation_trials_table.php',
            '2026_digifest_007_create_competition_manual_verifications_table.php',
        ] as $migrationFile) {
            $migration = require base_path('database/migrations/'.$migrationFile);
            $migration->up();
        }

        DB::table('siswa')->insert([
            'nisn' => self::NISN,
            'nama_lengkap' => 'Siswa Uji',
            'kelas' => 'XII',
            'password' => 'test-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('school_settings')->insert([
            'latitude' => -7.671325,
            'longitude' => 110.589607,
            'radius_meters' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        require app_path('Competition/Routes/web.php');
        RouteFacade::getRoutes()->refreshNameLookups();
        RouteFacade::getRoutes()->refreshActionLookups();
        Auth::guard('siswa')->setUser(Siswa::query()->findOrFail(self::NISN));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_score_50_is_queued_without_writing_attendance_until_admin_approves(): void
    {
        $this->submitReviewAttempt();
        $verification = DB::table('competition_manual_verifications')->first();

        $this->assertSame(50, (int) $verification->score);
        $this->assertDatabaseHas('competition_trust_scores', [
            'id' => $verification->trust_score_id,
            'nisn' => self::NISN,
            'score' => 50,
            'verdict' => 'queued',
            'is_shadow' => false,
        ]);
        $this->assertSame('pending', $verification->status);
        $this->assertSame('in', $verification->absen_type);
        $this->assertDatabaseCount('presensi', 0);
        Storage::disk('local')->assertExists($verification->foto_tangkapan);

        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.invalid',
            'password' => 'test-password',
        ]);
        $this->actingAs($admin, 'user');
        $this->get(route('competition.reviews.index'))
            ->assertOk()
            ->assertSee('Antrean Verifikasi Manual (Trust Engine)')
            ->assertSee('Siswa Uji');
        $this->get(route('competition.reviews.manual.snapshot', $verification->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->assertDatabaseHas('competition_audit_logs', [
            'event_type' => 'manual_verification_snapshot_accessed',
            'subject_nisn' => self::NISN,
        ]);
        $this->from(route('competition.reviews.index'))
            ->post(route('competition.reviews.manual.decide', $verification->id), [
                'decision' => 'approved',
                'catatan' => 'Diperiksa oleh admin.',
            ])
            ->assertRedirect(route('competition.reviews.index'));

        $this->assertDatabaseHas('competition_manual_verifications', [
            'id' => $verification->id,
            'status' => 'approved',
            'admin_id' => (string) $admin->id,
        ]);
        $attendance = DB::table('presensi')->where('nisn', self::NISN)->first();
        $this->assertNotNull($attendance);
        $this->assertSame('07:15:00', $attendance->jam_in);
        $this->assertSame('-7.671325,110.589607', $attendance->lokasi_in);
        Storage::disk('public')->assertExists('uploads/absensi/'.$attendance->foto_in);

        $secondAttempt = $this->submitReviewAttempt();
        $secondVerification = DB::table('competition_manual_verifications')
            ->where('challenge_id', $secondAttempt->id)
            ->first();
        $this->assertSame('out', $secondVerification->absen_type);
        $this->assertDatabaseCount('presensi', 1);

        $this->post(route('competition.reviews.manual.decide', $secondVerification->id), [
            'decision' => 'approved',
        ])->assertRedirect();
        $this->post(route('competition.reviews.manual.decide', $secondVerification->id), [
            'decision' => 'approved',
        ])->assertStatus(409);
        $completed = DB::table('presensi')->where('nisn', self::NISN)->first();
        $this->assertSame('07:15:00', $completed->jam_out);
        $this->assertNotEmpty($completed->foto_out);
        $this->assertDatabaseCount('presensi', 1);
    }

    public function test_score_70_remains_in_the_manual_queue_at_default_acceptance_boundary(): void
    {
        config(['competition.liveness_challenge.weights.liveness' => 70]);

        $this->submitReviewAttempt();
        $verification = DB::table('competition_manual_verifications')->first();

        $this->assertSame(70, (int) $verification->score);
        $this->assertSame('pending', $verification->status);
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_admin_rejection_does_not_write_attendance_and_student_cannot_decide(): void
    {
        $this->submitReviewAttempt();
        $verification = DB::table('competition_manual_verifications')->first();
        $this->actingAs(Siswa::query()->findOrFail(self::NISN), 'siswa');

        $this->get(route('competition.reviews.index'))->assertRedirect(route('loginadmin'));
        $this->get(route('competition.reviews.manual.snapshot', $verification->id))
            ->assertRedirect(route('loginadmin'));
        $this->post(route('competition.reviews.manual.decide', $verification->id), [
            'decision' => 'approved',
        ])->assertRedirect(route('loginadmin'));
        $this->assertDatabaseHas('competition_manual_verifications', [
            'id' => $verification->id,
            'status' => 'pending',
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin2@example.invalid',
            'password' => 'test-password',
        ]);
        $this->actingAs($admin, 'user');
        $this->post(route('competition.reviews.manual.decide', $verification->id), [
            'decision' => 'rejected',
            'catatan' => 'Bukti tidak memadai.',
        ])->assertRedirect();

        $this->assertDatabaseHas('competition_manual_verifications', [
            'id' => $verification->id,
            'status' => 'rejected',
            'admin_id' => (string) $admin->id,
        ]);
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_admin_cannot_approve_after_consent_is_withdrawn(): void
    {
        config([
            'competition.consent_gate' => true,
            'competition.consent.text_approved' => true,
            'competition.consent.version' => 'consent-1',
            'competition.consent.text' => 'Consent test',
        ]);
        DB::table('competition_consents')->insert([
            'siswa_nisn' => self::NISN,
            'status' => 'granted',
            'text_version' => 'consent-1',
            'text_hash' => hash('sha256', 'Consent test'),
            'actor_type' => 'admin',
            'actor_id' => '1',
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->submitReviewAttempt();
        $verification = DB::table('competition_manual_verifications')->first();
        DB::table('competition_consents')->insert([
            'siswa_nisn' => self::NISN,
            'status' => 'withdrawn',
            'text_version' => 'consent-1',
            'text_hash' => hash('sha256', 'Consent test'),
            'actor_type' => 'admin',
            'actor_id' => '1',
            'recorded_at' => now()->addSecond(),
            'created_at' => now()->addSecond(),
            'updated_at' => now()->addSecond(),
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin3@example.invalid',
            'password' => 'test-password',
        ]);
        $this->actingAs($admin, 'user');
        $this->post(route('competition.reviews.manual.decide', $verification->id), [
            'decision' => 'approved',
        ])->assertStatus(409);

        $this->assertDatabaseHas('competition_manual_verifications', [
            'id' => $verification->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_disabled_enforcement_does_not_query_queue_or_change_original_response(): void
    {
        config(['competition.trust_engine_enforce' => false]);
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'competition_manual_verifications')) {
                $queries++;
            }
        });
        $request = Request::create('/presensi/face/store', 'POST');
        $called = false;
        $response = (new EnforceMiddleware)->handle(
            $request,
            function () use (&$called): JsonResponse {
                $called = true;

                return response()->json(['status' => 'success', 'message' => 'legacy']);
            }
        );

        $this->assertTrue($called);
        $this->assertSame('legacy', $response->getData(true)['message']);
        $this->assertSame(0, $queries);
        $this->assertDatabaseCount('competition_manual_verifications', 0);
    }

    private function submitReviewAttempt(): LivenessChallenge
    {
        $challenge = app(LivenessChallengeService::class)->issue(self::NISN, self::SESSION_ID);
        $challengeRecord = LivenessChallenge::query()
            ->where('nonce_hash', hash('sha256', $challenge['nonce']))
            ->firstOrFail();
        $delta = match ($challenge['instruction']) {
            'turn_left' => -0.2,
            default => 0.2,
        };
        $request = Request::create('/presensi/face/store', 'POST', [
            'challenge_nonce' => $challenge['nonce'],
            'liveness_status' => 'passed',
            'liveness_evidence' => ['delta' => $delta, 'elapsed_ms' => 1200],
            'nisn_match' => self::NISN,
            'confidence' => 0.2,
            'lokasi' => '-7.671325,110.589607',
            'image' => self::JPEG,
        ]);
        $request->setLaravelSession($this->createSession(self::SESSION_ID));
        $request->setRouteResolver(fn () => (new Route('POST', '/presensi/face/store', []))->name('presensi.face.store'));
        $controllerCalled = false;
        $response = (new EnforceMiddleware)->handle(
            $request,
            function () use (&$controllerCalled): JsonResponse {
                $controllerCalled = true;

                return response()->json(['status' => 'success']);
            }
        );

        $this->assertFalse($controllerCalled);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pending', $response->getData(true)['verification_status']);

        return $challengeRecord->fresh();
    }

    private function createSession(string $id): Store
    {
        $session = new Store('manual-verification-test', new ArraySessionHandler(120));
        $session->setId($id);
        $session->start();

        return $session;
    }
}
