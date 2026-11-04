<?php

namespace Tests\Feature;

use App\Competition\Models\EvaluationTrial;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompetitionEvaluationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'competition.ai_evaluation_dashboard' => true,
            'competition.evaluation.small_sample_threshold' => 20,
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

        foreach ([
            '2026_digifest_003_create_competition_liveness_challenges_table.php',
            '2026_digifest_006_create_competition_evaluation_trials_table.php',
        ] as $migrationFile) {
            $migration = require base_path('database/migrations/'.$migrationFile);
            $migration->up();
        }

        require app_path('Competition/Routes/evaluation.php');
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    public function test_dashboard_has_an_honest_empty_state_and_rejects_student_guard(): void
    {
        $this->actingAs($this->createAdmin(), 'user');
        $this->get(route('competition.evaluation.index'))
            ->assertOk()
            ->assertSee('n = 0')
            ->assertSee('Belum ada percobaan uji manual')
            ->assertSee('Tidak ada angka FAR, FRR')
            ->assertSee('—');

        $student = Siswa::query()->create([
            'nisn' => '9999000001',
            'nama_lengkap' => 'Siswa Uji',
            'kelas' => 'XII',
            'password' => 'test-password',
        ]);
        Auth::guard('user')->logout();
        $this->actingAs($student, 'siswa');
        $this->get(route('competition.evaluation.index'))
            ->assertRedirect(route('loginadmin'));
    }

    public function test_dashboard_metrics_use_recorded_trials_and_exclude_review_from_denominators(): void
    {
        $this->seedChallenge('accepted', 'passed', null, null);
        $this->seedChallenge('rejected', 'failed', 'liveness_proof_invalid', 'Bukti challenge tidak valid.');
        foreach ([
            ['genuine', 'accepted', 'challenge_lulus', 'T1', '2026-10-30', null],
            ['genuine', 'rejected', 'liveness_gagal', 'T1', '2026-10-30', 'instruksi tidak terpenuhi'],
            ['genuine', 'review', 'kamera_error', 'T1', '2026-10-30', null],
            ['print', 'rejected', 'foto_cetak_ditolak', 'T2', '2026-10-31', null],
            ['print', 'review', 'evaluator_error', 'T2', '2026-10-31', null],
            ['screen', 'accepted', 'foto_layar_diterima', 'T2', '2026-11-01', null],
            ['screen', 'rejected', 'foto_layar_ditolak', 'T2', '2026-11-01', null],
        ] as $row) {
            $this->storeTrial($row);
        }

        $this->actingAs($this->createAdmin(), 'user');
        $this->get(route('competition.evaluation.index'))
            ->assertOk()
            ->assertSee('7')
            ->assertSee('Sampel kecil')
            ->assertSee('50%')
            ->assertSee('100%')
            ->assertSee('liveness_proof_invalid')
            ->assertSee('passed: 1')
            ->assertSee('failed: 1');
    }

    public function test_csv_import_is_validated_atomic_and_idempotent(): void
    {
        $validCsv = $this->writeCsv([
            ['genuine', 'accepted', 'challenge_lulus', 'T1', '2026-11-01', 'instruksi selesai'],
        ]);
        $invalidCsv = $this->writeCsv([
            ['print', 'accepted', 'foto diterima', 'Nama Penguji', '2026-11-01', ''],
            ['screen', 'accepted', 'email tester@example.invalid', 'T2', '2026-11-01', ''],
        ]);

        try {
            $this->assertSame(0, Artisan::call('competition:import-evaluation-trials', ['file' => $validCsv]));
            $this->assertSame(1, EvaluationTrial::query()->count());
            $this->assertSame(0, Artisan::call('competition:import-evaluation-trials', ['file' => $validCsv]));
            $this->assertSame(1, EvaluationTrial::query()->count());
            $this->assertStringContainsString('1 exact duplicate row(s) skipped', Artisan::output());

            $this->assertSame(1, Artisan::call('competition:import-evaluation-trials', ['file' => $invalidCsv]));
            $this->assertSame(1, EvaluationTrial::query()->count());
            $this->assertStringContainsString('No rows were imported', Artisan::output());
            $this->assertStringNotContainsString('tester@example.invalid', Artisan::output());
        } finally {
            @unlink($validCsv);
            @unlink($invalidCsv);
        }
    }

    public function test_snapshot_export_has_no_personal_columns_and_reports_empty_data_truthfully(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'evaluation-snapshot-'.bin2hex(random_bytes(5));

        try {
            $this->assertSame(0, Artisan::call('competition:export-evaluation-snapshot', ['--directory' => $directory]));
            $csv = file_get_contents($directory.DIRECTORY_SEPARATOR.'manual_trials.csv');
            $methodology = file_get_contents($directory.DIRECTORY_SEPARATOR.'METHODOLOGY.md');
            $this->assertSame("trial_type,outcome,reason,tester_code,tested_at,notes\n", $csv);
            $this->assertStringContainsString('Total recorded trials (n): 0', $methodology);
            $this->assertStringContainsString('FAR, FRR, and attack rejection rates are unavailable', $methodology);

            $this->storeTrial(['genuine', 'accepted', 'challenge_lulus', 'T1', '2026-11-02', 'relawan menyelesaikan instruksi']);
            $this->assertSame(0, Artisan::call('competition:export-evaluation-snapshot', ['--directory' => $directory]));
            $csv = file_get_contents($directory.DIRECTORY_SEPARATOR.'manual_trials.csv');

            $this->assertStringContainsString('genuine,accepted,challenge_lulus,T1,2026-11-02', $csv);
            $this->assertStringNotContainsString('name', strtolower($csv));
            $this->assertStringNotContainsString('descriptor', strtolower($csv));
            $this->assertStringNotContainsString('NISN', $csv);
        } finally {
            foreach (['manual_trials.csv', 'METHODOLOGY.md'] as $name) {
                $path = $directory.DIRECTORY_SEPARATOR.$name;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.invalid',
            'password' => 'test-password',
        ]);
    }

    private function seedChallenge(string $verdict, string $livenessStatus, ?string $reasonCode, ?string $reason): void
    {
        static $counter = 0;
        $counter++;

        DB::table('competition_liveness_challenges')->insert([
            'nonce_hash' => hash('sha256', 'nonce-'.$counter),
            'siswa_nisn' => '9999000001',
            'session_hash' => str_repeat((string) $counter, 64),
            'instruction' => 'blink',
            'expires_at' => now()->addMinute(),
            'liveness_status' => $livenessStatus,
            'verdict' => $verdict,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string, 4: string, 5: ?string}  $values
     */
    private function storeTrial(array $values): void
    {
        $trial = [
            'trial_type' => $values[0],
            'outcome' => $values[1],
            'reason' => $values[2],
            'tester_code' => $values[3],
            'tested_at' => $values[4],
            'notes' => $values[5],
        ];
        $trial['fingerprint'] = EvaluationTrial::fingerprintFor($trial);
        EvaluationTrial::query()->create($trial);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>  $rows
     */
    private function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'evaluation-csv-');
        $handle = fopen($path, 'wb');
        fputcsv($handle, ['trial_type', 'outcome', 'reason', 'tester_code', 'tested_at', 'notes'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);

        return $path;
    }
}
