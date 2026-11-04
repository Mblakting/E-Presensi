<?php

namespace Tests\Feature;

use App\Competition\Http\ConsentGateMiddleware;
use App\Competition\Services\ConsentService;
use App\Models\Siswa;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompetitionStudentConsentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'competition.competition_nav' => true,
            'competition.consent_gate' => true,
            'competition.consent.text_approved' => false,
            'competition.consent.version' => 'consent-test-1',
            'competition.consent.text' => 'Teks persetujuan uji.',
        ]);
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
        });

        foreach ([
            '2026_digifest_002_create_competition_audit_logs_table.php',
            '2026_digifest_005_create_competition_consents_table.php',
        ] as $migrationFile) {
            $migration = require base_path('database/migrations/'.$migrationFile);
            $migration->up();
        }
        require app_path('Competition/Routes/consent.php');
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
        Route::getRoutes()->getByName('presensi.face')->middleware(ConsentGateMiddleware::class);
        DB::table('siswa')->insert([
            'nisn' => '9999000001',
            'nama_lengkap' => 'Siswa Uji',
            'kelas' => 'XII',
            'password' => 'test-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_student_gets_a_clear_consent_page_and_draft_cannot_be_granted(): void
    {
        $student = Siswa::query()->findOrFail('9999000001');
        $this->actingAs($student, 'siswa');

        $this->get(route('competition.student-consents.index'))
            ->assertOk()
            ->assertSee('DRAF — belum dapat digunakan')
            ->assertSee('berusia 18 tahun atau lebih')
            ->assertDontSee('9999000001');
        $this->post(route('competition.student-consents.grant'), [
            'adult_volunteer_verified' => '1',
        ])->assertRedirect()
            ->assertSessionHas('warning', 'Teks persetujuan masih draf. Persetujuan belum dapat dicatat.');
        $this->assertDatabaseCount('competition_consents', 0);
    }

    public function test_student_can_record_and_withdraw_only_their_own_adult_volunteer_consent(): void
    {
        config(['competition.consent.text_approved' => true]);
        $student = Siswa::query()->findOrFail('9999000001');
        $this->actingAs($student, 'siswa');

        $this->post(route('competition.student-consents.grant'), [
            'adult_volunteer_verified' => '1',
            'nisn' => '9999000002',
            'authority_basis' => 'school_guardian_record',
        ])->assertRedirect();
        $this->assertDatabaseHas('competition_consents', [
            'siswa_nisn' => '9999000001',
            'status' => 'granted',
            'actor_type' => 'student',
            'actor_id' => '9999000001',
            'authority_basis' => 'adult_volunteer',
            'text_version' => 'consent-test-1',
        ]);
        $this->assertDatabaseMissing('competition_consents', [
            'siswa_nisn' => '9999000002',
        ]);
        $this->assertTrue(app(ConsentService::class)->isGranted('9999000001'));

        $request = Request::create('/presensi/face');
        $request->setRouteResolver(fn () => Route::getRoutes()->getByName('presensi.face'));
        $response = app(ConsentGateMiddleware::class)->handle($request, fn () => response('unexpected'));
        $this->assertSame(200, $response->getStatusCode());

        $this->post(route('competition.student-consents.withdraw'))->assertRedirect();
        $this->assertDatabaseHas('competition_consents', [
            'siswa_nisn' => '9999000001',
            'status' => 'withdrawn',
            'actor_type' => 'student',
        ]);
        $this->assertFalse(app(ConsentService::class)->isGranted('9999000001'));
    }

    public function test_unconsented_face_gate_redirects_student_to_own_consent_page(): void
    {
        $student = Siswa::query()->findOrFail('9999000001');
        $this->actingAs($student, 'siswa');

        $this->get(route('presensi.face'))
            ->assertRedirect(route('competition.student-consents.index'))
            ->assertSessionHas('warning');
    }

    public function test_navigation_off_preserves_existing_consent_gate_fallback(): void
    {
        config(['competition.competition_nav' => false]);
        $student = Siswa::query()->findOrFail('9999000001');
        $this->actingAs($student, 'siswa');

        $this->get(route('presensi.face'))
            ->assertRedirect(route('presensi.create'))
            ->assertSessionHas(
                'warning',
                'Persetujuan pemrosesan wajah belum aktif. Gunakan presensi non-wajah atau hubungi pengelola.'
            );
    }
}
