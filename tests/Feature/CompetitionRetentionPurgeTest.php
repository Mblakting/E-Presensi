<?php

namespace Tests\Feature;

use App\Competition\Models\EvaluationTrial;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompetitionRetentionPurgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'competition.retention.trust_scores_days' => 90,
            'competition.retention.liveness_days' => 90,
            'competition.retention.audit_days' => 90,
            'competition.retention.consent_history_days' => 365,
            'competition.evaluation.retention_days' => 180,
        ]);
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
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

        DB::table('siswa')->insert(['nisn' => '9999000001']);
        DB::table('presensi')->insert(['nisn' => '9999000001']);
        $this->seedData();
    }

    public function test_dry_run_and_purge_respect_retention_and_keep_latest_consent_without_touching_client_rows(): void
    {
        $this->artisan('competition:purge-liveness-data', ['--dry-run' => true])
            ->expectsOutputToContain('1 challenge(s), 1 review item(s), 1 audit event(s), 2 superseded consent record(s), and 1 evaluation trial(s)')
            ->assertExitCode(0);
        $this->assertSame(4, DB::table('competition_consents')->count());
        $this->assertSame(2, EvaluationTrial::query()->count());

        $this->artisan('competition:purge-liveness-data')
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('competition_liveness_challenges')->count());
        $this->assertSame(1, DB::table('competition_verification_queue')->count());
        $this->assertSame(2, DB::table('competition_audit_logs')->count());
        $this->assertSame(2, DB::table('competition_consents')->count());
        $this->assertDatabaseHas('competition_consents', [
            'siswa_nisn' => '9999000001',
            'status' => 'withdrawn',
        ]);
        $this->assertSame(1, EvaluationTrial::query()->count());
        $this->assertDatabaseHas('competition_audit_logs', ['event_type' => 'competition_data_purged']);
        $this->assertSame(1, DB::table('siswa')->count());
        $this->assertSame(1, DB::table('presensi')->count());
    }

    public function test_invalid_configured_retention_is_rejected(): void
    {
        config(['competition.evaluation.retention_days' => 0]);

        $this->artisan('competition:purge-liveness-data', ['--dry-run' => true])
            ->expectsOutputToContain('positive integer')
            ->assertExitCode(1);
    }

    private function seedData(): void
    {
        $old = now()->subDays(100);
        $fresh = now()->subDays(10);
        foreach ([$old, $fresh] as $createdAt) {
            $challengeId = DB::table('competition_liveness_challenges')->insertGetId([
                'nonce_hash' => hash('sha256', (string) $createdAt),
                'siswa_nisn' => '9999000001',
                'session_hash' => hash('sha256', 'session-'.$createdAt),
                'instruction' => 'blink',
                'expires_at' => $createdAt->copy()->addMinute(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            DB::table('competition_verification_queue')->insert([
                'challenge_id' => $challengeId,
                'siswa_nisn' => '9999000001',
                'absen_type' => 'in',
                'score' => 60,
                'reason_code' => 'score_requires_review',
                'reason' => 'Skor memerlukan verifikasi.',
                'status' => 'pending',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            DB::table('competition_audit_logs')->insert([
                'event_type' => 'verification_queued',
                'actor_type' => 'system',
                'subject_nisn' => '9999000001',
                'is_synthetic' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        DB::table('competition_consents')->insert([
            $this->consentRow('9999000001', 'granted', now()->subDays(400)),
            $this->consentRow('9999000001', 'withdrawn', now()->subDays(380)),
            $this->consentRow('9999000002', 'withdrawn', now()->subDays(400)),
            $this->consentRow('9999000002', 'granted', now()->subDays(20)),
        ]);
        foreach ([now()->subDays(200), now()->subDays(20)] as $date) {
            $trial = [
                'trial_type' => 'genuine',
                'outcome' => 'accepted',
                'reason' => 'challenge_lulus',
                'tester_code' => 'T1',
                'tested_at' => $date->toDateString(),
                'notes' => null,
            ];
            DB::table('competition_evaluation_trials')->insert([
                ...$trial,
                'fingerprint' => \App\Competition\Models\EvaluationTrial::fingerprintFor($trial),
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    private function consentRow(string $nisn, string $status, \Carbon\CarbonInterface $date): array
    {
        return [
            'siswa_nisn' => $nisn,
            'status' => $status,
            'text_version' => 'draft-1',
            'text_hash' => hash('sha256', 'consent'),
            'actor_type' => 'admin',
            'actor_id' => '1',
            'recorded_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }
}
