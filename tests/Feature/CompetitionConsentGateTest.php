<?php

namespace Tests\Feature;

use App\Competition\Http\ConsentGateMiddleware;
use App\Competition\Services\ConsentService;
use App\Http\Controllers\FaceRecognitionController;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class CompetitionConsentGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'competition.consent_gate' => true,
            'competition.consent.text_approved' => false,
            'competition.consent.version' => 'draft-1',
            'competition.consent.text' => 'Teks uji persetujuan',
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
        });

        foreach ([
            '2026_digifest_002_create_competition_audit_logs_table.php',
            '2026_digifest_005_create_competition_consents_table.php',
        ] as $migrationFile) {
            $migration = require base_path('database/migrations/'.$migrationFile);
            $migration->up();
        }
        require app_path('Competition/Routes/consent.php');
        require app_path('Competition/Routes/web.php');
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        foreach ([
            'face.enroll',
            'face.save',
            'batch.face.extract',
            'presensi.face',
            'presensi.face.store',
            'face.descriptors',
        ] as $routeName) {
            Route::getRoutes()->getByName($routeName)->middleware(ConsentGateMiddleware::class);
        }

        foreach (['9999000001', '9999000002'] as $nisn) {
            DB::table('siswa')->insert([
                'nisn' => $nisn,
                'nama_lengkap' => 'Siswa Uji',
                'kelas' => 'XII',
                'password' => 'test-password',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_draft_consent_cannot_be_recorded_until_reviewed(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'user');

        $this->post(route('competition.consents.grant'), [
            'nisn' => '9999000001',
            'authority_basis' => 'adult_volunteer',
            'authority_verified' => '1',
        ])->assertRedirect()->assertSessionHas('warning', 'Draf persetujuan belum ditinjau dan disetujui; persetujuan belum dapat dicatat.');

        $this->assertDatabaseCount('competition_consents', 0);
    }

    public function test_grant_withdrawal_and_new_text_version_are_append_only(): void
    {
        config(['competition.consent.text_approved' => true]);
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'user');

        $this->post(route('competition.consents.grant'), [
            'nisn' => '9999000001',
            'authority_basis' => 'adult_volunteer',
            'authority_verified' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('competition_consents', [
            'siswa_nisn' => '9999000001',
            'status' => 'granted',
            'text_version' => 'draft-1',
            'text_hash' => hash('sha256', 'Teks uji persetujuan'),
        ]);
        $this->assertDatabaseHas('competition_audit_logs', [
            'event_type' => 'consent_granted',
            'subject_nisn' => '9999000001',
        ]);

        config([
            'competition.consent.version' => 'draft-2',
            'competition.consent.text' => 'Teks uji versi baru',
        ]);
        $this->assertFalse(app(ConsentService::class)->isGranted('9999000001'));
        $this->post(route('competition.consents.grant'), [
            'nisn' => '9999000001',
            'authority_basis' => 'school_guardian_record',
            'authority_verified' => '1',
        ])->assertRedirect();

        $this->assertDatabaseCount('competition_consents', 2);
        $this->assertDatabaseHas('competition_consents', [
            'siswa_nisn' => '9999000001',
            'status' => 'granted',
            'text_version' => 'draft-2',
            'text_hash' => hash('sha256', 'Teks uji versi baru'),
            'authority_basis' => 'school_guardian_record',
        ]);
        $this->assertTrue(app(ConsentService::class)->isGranted('9999000001'));

        $this->post(route('competition.consents.withdraw'), ['nisn' => '9999000001'])->assertRedirect();
        $this->assertDatabaseCount('competition_consents', 3);
        $this->assertDatabaseCount('competition_audit_logs', 3);
        $this->assertDatabaseHas('competition_consents', [
            'siswa_nisn' => '9999000001',
            'status' => 'withdrawn',
        ]);
        $this->assertFalse(app(ConsentService::class)->isGranted('9999000001'));
    }

    public function test_student_cannot_open_or_change_admin_consent_records(): void
    {
        $this->actingAs(Siswa::query()->findOrFail('9999000001'), 'siswa');

        $this->get(route('competition.consents.index'))->assertRedirect(route('loginadmin'));
        $this->post(route('competition.consents.grant'), [
            'nisn' => '9999000001',
            'authority_basis' => 'adult_volunteer',
            'authority_verified' => '1',
        ])->assertRedirect(route('loginadmin'));
        $this->post(route('competition.consents.withdraw'), ['nisn' => '9999000001'])
            ->assertRedirect(route('loginadmin'));

        $this->assertDatabaseCount('competition_consents', 0);
    }

    public function test_unconsented_face_enrollment_and_attendance_stop_before_client_controller(): void
    {
        $this->actingAs($this->createAdmin(), 'user');
        $this->get(route('face.enroll', '9999000001'))
            ->assertRedirect(route('competition.consents.index'))
            ->assertSessionHas('warning');

        $this->postJson(route('face.save'), [
            'nisn' => '9999000001',
            'descriptor' => array_fill(0, 128, 0.1),
        ])->assertForbidden()->assertJsonPath('status', 'error');
        $this->assertDatabaseHas('siswa', [
            'nisn' => '9999000001',
            'face_descriptor' => null,
        ]);

        $this->actingAs(Siswa::query()->findOrFail('9999000001'), 'siswa');
        $this->postJson(route('presensi.face.store'))->assertForbidden();
        $this->get(route('presensi.face'))
            ->assertRedirect(route('competition.student-consents.index'))
            ->assertSessionHas('warning');
        $this->getJson(route('face.descriptors'))->assertForbidden();
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_descriptor_endpoint_only_returns_faces_with_current_consent(): void
    {
        config(['competition.consent.text_approved' => true]);
        app(ConsentService::class)->record('9999000001', 'granted', 'adult_volunteer');
        DB::table('siswa')->where('nisn', '9999000001')->update([
            'face_descriptor' => json_encode(array_fill(0, 128, 0.1)),
        ]);
        DB::table('siswa')->where('nisn', '9999000002')->update([
            'face_descriptor' => json_encode(array_fill(0, 128, 0.2)),
        ]);
        $this->mock(FaceRecognitionController::class, function ($mock): void {
            $mock->shouldNotReceive('getDescriptors');
        });

        $this->actingAs(Siswa::query()->findOrFail('9999000001'), 'siswa');
        $this->getJson(route('face.descriptors'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nisn', '9999000001');
    }

    public function test_existing_grant_does_not_open_face_gate_while_text_is_unapproved(): void
    {
        config(['competition.consent.text_approved' => true]);
        app(ConsentService::class)->record('9999000001', 'granted', 'adult_volunteer');
        config(['competition.consent.text_approved' => false]);

        $this->actingAs(Siswa::query()->findOrFail('9999000001'), 'siswa');

        $this->assertFalse(app(ConsentService::class)->isGranted('9999000001'));
        $this->get(route('presensi.face'))
            ->assertRedirect(route('competition.student-consents.index'))
            ->assertSessionHas('warning');
    }

    public function test_bulk_zip_with_unconsented_student_is_rejected_before_extraction(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZIP extension is unavailable.');
        }

        Storage::fake('public');
        $zipPath = tempnam(sys_get_temp_dir(), 'consent-zip-');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::OVERWRITE));
        $zip->addFromString('9999000001.jpg', 'synthetic image content');
        $zip->close();

        try {
            $this->actingAs($this->createAdmin(), 'user');
            $this->withHeader('Accept', 'application/json')->post(route('batch.face.extract'), [
                'file_zip' => new UploadedFile($zipPath, 'faces.zip', 'application/zip', null, true),
            ])->assertForbidden()->assertJsonPath('message', 'Ekstraksi ZIP dibatalkan sebelum file diekstrak: persetujuan wajah belum aktif untuk 1 siswa.');

            Storage::disk('public')->assertMissing('temp_faces/9999000001.jpg');
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_admin_consent_screen_discloses_draft_status_and_hash(): void
    {
        $this->actingAs($this->createAdmin(), 'user');

        $this->get(route('competition.consents.index'))
            ->assertOk()
            ->assertSee('DRAF — belum boleh digunakan')
            ->assertSee(hash('sha256', 'Teks uji persetujuan'))
            ->assertSee('Teks uji persetujuan');
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.invalid',
            'password' => 'test-password',
        ]);
    }
}
