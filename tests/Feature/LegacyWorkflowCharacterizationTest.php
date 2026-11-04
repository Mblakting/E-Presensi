<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyWorkflowCharacterizationTest extends TestCase
{
    private const NISN = '9999000001';
    private const LOCATION = '-7.671325,110.589607';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'competition.trust_engine_shadow' => false,
            'competition.trust_engine_enforce' => false,
            'competition.ai_evaluation_dashboard' => false,
            'competition.anomaly_detection' => false,
            'competition.competition_menu' => false,
        ]);
        Carbon::setTestNow('2026-11-04 07:15:00');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('jurusan', function (Blueprint $table): void {
            $table->string('kode_jurusan')->primary();
            $table->string('nama_jurusan');
            $table->timestamps();
        });
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
            $table->string('nama_lengkap');
            $table->string('kelas');
            $table->string('no_hp')->nullable();
            $table->text('alamat')->nullable();
            $table->string('nama_orang_tua')->nullable();
            $table->string('foto')->nullable();
            $table->string('kode_jurusan')->nullable();
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
            $table->string('foto_in')->nullable();
            $table->string('foto_out')->nullable();
            $table->string('lokasi_in')->nullable();
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

        DB::table('jurusan')->insert([
            'kode_jurusan' => 'TKJ',
            'nama_jurusan' => 'Teknik Komputer',
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
        Siswa::query()->create([
            'nisn' => self::NISN,
            'nama_lengkap' => 'Siswa Uji Sintetis',
            'kelas' => 'XII TKJ 1',
            'kode_jurusan' => 'TKJ',
            'password' => 'test-password',
        ]);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_existing_student_login_redirects_to_dashboard(): void
    {
        $this->post('/proseslogin', [
            'nisn' => self::NISN,
            'password' => 'test-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated('siswa');
    }

    public function test_existing_attendance_flow_records_in_and_out(): void
    {
        $student = Siswa::query()->findOrFail(self::NISN);
        $this->actingAs($student, 'siswa');

        $this->postJson('/presensi/store', $this->attendancePayload())
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('type', 'in');

        $this->assertDatabaseHas('presensi', [
            'nisn' => self::NISN,
            'tgl_presensi' => '2026-11-04',
            'jam_out' => null,
        ]);

        $this->postJson('/presensi/store', $this->attendancePayload())
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('type', 'out');

        $this->assertDatabaseHas('presensi', [
            'nisn' => self::NISN,
            'tgl_presensi' => '2026-11-04',
            'jam_out' => '07:15:00',
        ]);
    }

    public function test_existing_geofence_rejects_out_of_radius_location(): void
    {
        $this->actingAs(Siswa::query()->findOrFail(self::NISN), 'siswa');

        $this->postJson('/presensi/store', $this->attendancePayload('0,0'))
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('type', 'out');

        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_existing_attendance_report_contains_period_totals(): void
    {
        User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin-test@example.invalid',
            'password' => 'test-password',
        ]);
        DB::table('presensi')->insert([
            'nisn' => self::NISN,
            'tgl_presensi' => '2026-11-04',
            'jam_in' => '07:15:00',
            'jam_out' => '15:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs(User::query()->firstOrFail(), 'user');

        $this->post('/laporan/rekap', [
            'tgl_mulai' => '2026-11-04',
            'tgl_selesai' => '2026-11-04',
        ])
            ->assertOk()
            ->assertViewHas('hariKerja', 1)
            ->assertViewHas('rekap', function ($rekap): bool {
                return $rekap->count() === 1
                    && $rekap->first()->total_hadir === 1
                    && $rekap->first()->total_pulang === 1;
            });
    }

    private function attendancePayload(?string $location = null): array
    {
        return [
            'lokasi' => $location ?? self::LOCATION,
            'image' => 'data:image/jpeg;base64,' . base64_encode('synthetic-test-image'),
        ];
    }
}
