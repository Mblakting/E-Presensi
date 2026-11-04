<?php

namespace Tests\Feature;

use App\Competition\Http\CompetitionErrorMiddleware;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompetitionNavigationPagesTest extends TestCase
{
    private const ADMIN_PAGES = [
        'competition.evaluation.index',
        'competition.reviews.index',
        'competition.audit.index',
        'competition.consents.index',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'competition.competition_nav' => true,
            'competition.trust_engine_enforce' => true,
            'competition.ai_evaluation_dashboard' => true,
            'competition.audit_viewer' => true,
            'competition.consent_gate' => true,
        ]);
        $this->createClientTables();
        $this->runCompetitionMigrations();

        require app_path('Competition/Routes/web.php');
        require app_path('Competition/Routes/consent.php');
        require app_path('Competition/Routes/evaluation.php');
        require app_path('Competition/Routes/audit.php');
        require app_path('Competition/Routes/navigation.php');
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    public function test_guest_sees_public_guide_and_is_redirected_from_authenticated_hub(): void
    {
        $this->get('/competition/guide')
            ->assertOk()
            ->assertSee('Panduan Juri')
            ->assertSee('bukan verifikasi wajah server-side')
            ->assertDontSee('9999000001');
        $this->get('/competition')->assertRedirect(route('competition.guide'));
    }

    public function test_admin_pages_are_closed_to_guests_and_students_but_open_to_admin(): void
    {
        foreach (self::ADMIN_PAGES as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('loginadmin'));
        }

        $student = Siswa::query()->create([
            'nisn' => '9999000001',
            'nama_lengkap' => 'Siswa Uji',
            'kelas' => 'XII',
            'password' => 'test-password',
        ]);
        $this->actingAs($student, 'siswa');
        foreach (self::ADMIN_PAGES as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('loginadmin'));
        }

        auth('siswa')->logout();
        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.invalid',
            'password' => 'test-password',
        ]);
        $this->actingAs($admin, 'user');
        foreach (self::ADMIN_PAGES as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_hub_shows_only_aggregate_status_for_each_authenticated_audience(): void
    {
        DB::table('siswa')->insert([
            'nisn' => '9999000001',
            'nama_lengkap' => 'Nama Privat',
            'kelas' => 'XII',
            'password' => 'test-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $student = Siswa::query()->findOrFail('9999000001');
        $this->actingAs($student, 'siswa');
        $this->get('/competition')
            ->assertOk()
            ->assertSee('Belum ada persetujuan')
            ->assertDontSee('9999000001')
            ->assertDontSee('Nama Privat');

        auth('siswa')->logout();
        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin-hub@example.invalid',
            'password' => 'test-password',
        ]);
        $this->actingAs($admin, 'user');
        $this->get('/competition')
            ->assertOk()
            ->assertSee('Dashboard Evaluasi AI')
            ->assertSee('Antrean Review')
            ->assertSee('Log Audit')
            ->assertSee('Persetujuan')
            ->assertDontSee('Nama Privat')
            ->assertDontSee('9999000001');
    }

    public function test_student_consent_page_is_not_available_to_admin_guard(): void
    {
        $student = Siswa::query()->create([
            'nisn' => '9999000002',
            'nama_lengkap' => 'Siswa Uji',
            'kelas' => 'XII',
            'password' => 'test-password',
        ]);
        $this->actingAs($student, 'siswa');
        $this->get(route('competition.student-consents.index'))->assertOk();

        auth('siswa')->logout();
        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin-consent@example.invalid',
            'password' => 'test-password',
        ]);
        $this->actingAs($admin, 'user');
        $this->get(route('competition.student-consents.index'))->assertRedirect(route('login'));
    }

    public function test_competition_error_pages_explain_forbidden_missing_and_unavailable_states(): void
    {
        $middleware = app(CompetitionErrorMiddleware::class);
        $messages = [
            403 => 'Akses tidak tersedia',
            404 => 'Halaman tidak ditemukan',
            503 => 'Fitur sementara tidak tersedia',
        ];

        foreach ($messages as $status => $title) {
            $response = $middleware->handle(
                Request::create('/competition/test-error'),
                static function () use ($status): void {
                    abort($status);
                }
            );

            $this->assertSame($status, $response->getStatusCode());
            $this->assertStringContainsString($title, (string) $response->getContent());
            $this->assertStringContainsString('Pusat fitur lomba', (string) $response->getContent());
        }
    }

    private function createClientTables(): void
    {
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
            $table->timestamps();
        });
        Schema::create('school_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->timestamps();
        });
    }

    private function runCompetitionMigrations(): void
    {
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
    }
}
