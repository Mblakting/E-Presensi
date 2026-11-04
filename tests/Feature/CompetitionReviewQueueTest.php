<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompetitionReviewQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['competition.trust_engine_enforce' => false]);
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
            $table->time('jam_out')->nullable();
            $table->unique(['nisn', 'tgl_presensi']);
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

        require app_path('Competition/Routes/web.php');
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
        Route::getRoutes()->getByName('presensi.face')->middleware(\App\Competition\Http\EnforceMiddleware::class);
        Route::getRoutes()->getByName('presensi.face.store')->middleware(\App\Competition\Http\EnforceMiddleware::class);
        DB::table('siswa')->insert([
            'nisn' => '9999000001',
            'nama_lengkap' => 'Siswa Uji',
            'kelas' => 'XII',
            'password' => 'test-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('presensi')->insert([
            'nisn' => '9999000001',
            'tgl_presensi' => now()->toDateString(),
            'jam_in' => '07:00:00',
        ]);
    }

    public function test_student_guard_cannot_access_review_page_or_decision_action(): void
    {
        $student = Siswa::query()->findOrFail('9999000001');
        $itemId = DB::table('competition_verification_queue')->insertGetId([
            'challenge_id' => 999,
            'siswa_nisn' => '9999000001',
            'absen_type' => 'in',
            'score' => 60,
            'reason_code' => 'score_requires_review',
            'reason' => 'Skor sinyal berada pada rentang verifikasi admin.',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($student, 'siswa');

        $this->get(route('competition.reviews.index'))->assertRedirect(route('loginadmin'));
        $this->post(route('competition.reviews.decide', $itemId), ['decision' => 'approved'])
            ->assertRedirect(route('loginadmin'));

        $this->assertDatabaseHas('competition_verification_queue', [
            'id' => $itemId,
            'status' => 'pending',
            'reviewer_id' => null,
        ]);
        $this->assertDatabaseCount('competition_audit_logs', 0);
    }

    public function test_admin_can_review_queue_and_only_competition_tables_are_changed(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.invalid',
            'password' => 'test-password',
        ]);
        $itemId = DB::table('competition_verification_queue')->insertGetId([
            'challenge_id' => 999,
            'siswa_nisn' => '9999000001',
            'absen_type' => 'in',
            'score' => 60,
            'reason_code' => 'score_requires_review',
            'reason' => 'Skor sinyal berada pada rentang verifikasi admin.',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($admin, 'user');

        $this->get(route('competition.reviews.index'))
            ->assertOk()
            ->assertSee('Antrean Verifikasi Presensi')
            ->assertSee('Skor sinyal berada pada rentang verifikasi admin.');
        $this->assertDatabaseHas('competition_audit_logs', [
            'event_type' => 'verification_queue_accessed',
            'actor_id' => (string) $admin->id,
        ]);
        $this->from(route('competition.reviews.index'))
            ->post(route('competition.reviews.decide', $itemId), ['decision' => 'suspicious'])
            ->assertRedirect(route('competition.reviews.index'));

        $this->assertDatabaseHas('competition_verification_queue', [
            'id' => $itemId,
            'status' => 'suspicious',
            'reviewer_id' => (string) $admin->id,
        ]);
        $this->assertDatabaseHas('competition_audit_logs', [
            'event_type' => 'verification_review_decision',
            'actor_type' => 'admin',
            'actor_id' => (string) $admin->id,
            'subject_nisn' => '9999000001',
        ]);
        $this->assertDatabaseHas('presensi', [
            'nisn' => '9999000001',
            'jam_in' => '07:00:00',
        ]);
        $this->assertDatabaseCount('presensi', 1);
    }

    public function test_student_can_fetch_challenge_and_authenticated_script(): void
    {
        $this->actingAs(Siswa::query()->findOrFail('9999000001'), 'siswa');

        $challenge = $this->post(route('competition.liveness.challenge'))
            ->assertOk()
            ->assertJsonStructure(['nonce', 'instruction', 'expires_in', 'proof_thresholds']);
        $this->assertStringContainsString('no-store', $challenge->headers->get('Cache-Control'));
        $nonce = $challenge->json('nonce');
        $this->assertDatabaseHas('competition_liveness_challenges', [
            'nonce_hash' => hash('sha256', $nonce),
            'siswa_nisn' => '9999000001',
        ]);

        $this->get(route('competition.liveness.script'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertSee('liveness_status', false);
    }

    public function test_real_attendance_page_html_receives_enforcement_script_without_editing_view(): void
    {
        config(['competition.trust_engine_enforce' => true]);
        $this->actingAs(Siswa::query()->findOrFail('9999000001'), 'siswa');

        $response = $this->get(route('presensi.face'))
            ->assertOk()
            ->assertSee(route('competition.liveness.script'), false);

        $bodyPosition = strpos($response->getContent(), '</body>');
        $scriptPosition = strpos($response->getContent(), '<script defer');
        $this->assertNotFalse($bodyPosition);
        $this->assertNotFalse($scriptPosition);
        $this->assertLessThan($bodyPosition, $scriptPosition);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_real_face_attendance_route_rejects_missing_nonce_before_controller(): void
    {
        config(['competition.trust_engine_enforce' => true]);
        $this->actingAs(Siswa::query()->findOrFail('9999000001'), 'siswa');

        $this->postJson(route('presensi.face.store'), [
            'nisn_match' => '9999000001',
            'confidence' => 0.2,
            'lokasi' => '-7.671325,110.589607',
            'image' => 'data:image/jpeg;base64,'.base64_encode('synthetic-image'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('type', 'challenge_unavailable');

        $this->assertDatabaseCount('presensi', 1);
        $this->assertDatabaseCount('competition_liveness_challenges', 0);
    }
}
