<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EraseSyntheticCompetitionSubjectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'competition.data_erasure' => true,
            'competition.consent_gate' => true,
        ]);
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
            $table->string('foto')->nullable();
            $table->text('face_descriptor')->nullable();
        });
        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('nisn');
        });

        foreach ([
            '2026_digifest_001_create_competition_trust_scores_table.php',
            '2026_digifest_002_create_competition_audit_logs_table.php',
            '2026_digifest_003_create_competition_liveness_challenges_table.php',
            '2026_digifest_004_create_competition_verification_queue_table.php',
            '2026_digifest_005_create_competition_consents_table.php',
            '2026_digifest_006_create_competition_evaluation_trials_table.php',
        ] as $migrationFile) {
            $migration = require base_path('database/migrations/'.$migrationFile);
            $migration->up();
        }

        foreach (['9999000001', '9999000002'] as $nisn) {
            DB::table('siswa')->insert([
                'nisn' => $nisn,
                'foto' => 'synthetic.jpg',
                'face_descriptor' => '[0.1,0.2]',
            ]);
            DB::table('presensi')->insert(['nisn' => $nisn]);
            $challengeId = DB::table('competition_liveness_challenges')->insertGetId([
                'nonce_hash' => hash('sha256', $nisn),
                'siswa_nisn' => $nisn,
                'session_hash' => hash('sha256', 'session-'.$nisn),
                'instruction' => 'blink',
                'expires_at' => now()->addMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('competition_verification_queue')->insert([
                'challenge_id' => $challengeId,
                'siswa_nisn' => $nisn,
                'absen_type' => 'in',
                'score' => 60,
                'reason_code' => 'score_requires_review',
                'reason' => 'Needs review',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('competition_trust_scores')->insert([
                'nisn' => $nisn,
                'score' => 60,
                'verdict' => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('competition_consents')->insert([
                'siswa_nisn' => $nisn,
                'status' => 'withdrawn',
                'text_version' => 'draft-1',
                'text_hash' => hash('sha256', 'consent'),
                'actor_type' => 'admin',
                'actor_id' => '1',
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('competition_audit_logs')->insert([
                'event_type' => 'consent_withdrawn',
                'actor_type' => 'admin',
                'subject_nisn' => $nisn,
                'is_synthetic' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $trial = [
            'trial_type' => 'genuine',
            'outcome' => 'accepted',
            'reason' => 'challenge_lulus',
            'tester_code' => 'T1',
            'tested_at' => now()->toDateString(),
            'notes' => null,
        ];
        DB::table('competition_evaluation_trials')->insert([
            ...$trial,
            'fingerprint' => \App\Competition\Models\EvaluationTrial::fingerprintFor($trial),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_erasure_is_flagged_synthetic_only_confirmed_audited_and_client_tables_are_untouched(): void
    {
        $this->artisan('competition:erase-synthetic-subject', [
            'nisn' => '9999000001',
            '--dry-run' => true,
        ])->expectsOutputToContain('competition_consents: 1')
            ->assertExitCode(0);
        $this->assertSame(2, DB::table('competition_consents')->count());

        $this->artisan('competition:erase-synthetic-subject', [
            'nisn' => '9999000001',
        ])->expectsOutputToContain('rerun with --dry-run')
            ->assertExitCode(1);
        $this->assertSame(2, DB::table('competition_trust_scores')->count());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->artisan('competition:erase-synthetic-subject', [
            'nisn' => '9999000001',
            '--confirm' => true,
        ])->expectsOutputToContain('no client-table data was changed.')
            ->assertExitCode(0);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        foreach ([
            'competition_trust_scores' => 'nisn',
            'competition_liveness_challenges' => 'siswa_nisn',
            'competition_verification_queue' => 'siswa_nisn',
            'competition_consents' => 'siswa_nisn',
        ] as $table => $column) {
            $this->assertSame(0, DB::table($table)->where($column, '9999000001')->count());
            $this->assertSame(1, DB::table($table)->where($column, '9999000002')->count());
        }
        $this->assertDatabaseHas('competition_audit_logs', [
            'event_type' => 'competition_subject_erased',
            'subject_nisn' => '9999000001',
            'is_synthetic' => true,
        ]);
        $this->assertSame(1, DB::table('competition_audit_logs')
            ->where('subject_nisn', '9999000001')
            ->where('event_type', 'competition_subject_erased')
            ->count());
        $this->assertSame(1, DB::table('competition_audit_logs')->where('subject_nisn', '9999000002')->count());
        $this->assertSame(1, DB::table('competition_evaluation_trials')->count());
        $this->assertSame(2, DB::table('siswa')->count());
        $this->assertSame(2, DB::table('presensi')->count());
        $this->assertSame(0, collect($queries)->filter(
            static fn (array $query): bool => preg_match(
                '/^\s*(?:insert|update|delete)\b.*\b(?:siswa|presensi)\b/i',
                $query['query']
            ) === 1
        )->count());
    }

    public function test_erasure_flag_and_synthetic_nisn_are_checked_before_database_access(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        config(['competition.data_erasure' => false]);
        $this->artisan('competition:erase-synthetic-subject', [
            'nisn' => '9999000001',
            '--confirm' => true,
        ])->expectsOutputToContain('is disabled')
            ->assertExitCode(1);
        $this->assertCount(0, DB::getQueryLog());

        config(['competition.data_erasure' => true]);
        config(['competition.consent_gate' => false]);
        $this->artisan('competition:erase-synthetic-subject', [
            'nisn' => '9999000001',
            '--confirm' => true,
        ])->expectsOutputToContain('is disabled')
            ->assertExitCode(1);
        $this->assertCount(0, DB::getQueryLog());

        config(['competition.consent_gate' => true]);
        $this->artisan('competition:erase-synthetic-subject', [
            'nisn' => '1234567890',
            '--confirm' => true,
        ])->expectsOutputToContain('only synthetic NISN')
            ->assertExitCode(1);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
