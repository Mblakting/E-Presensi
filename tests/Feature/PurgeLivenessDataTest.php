<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurgeLivenessDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $auditMigration = require base_path('database/migrations/2026_digifest_002_create_competition_audit_logs_table.php');
        $challengeMigration = require base_path('database/migrations/2026_digifest_003_create_competition_liveness_challenges_table.php');
        $queueMigration = require base_path('database/migrations/2026_digifest_004_create_competition_verification_queue_table.php');
        $auditMigration->up();
        $challengeMigration->up();
        $queueMigration->up();
    }

    public function test_dry_run_reports_records_without_deleting_and_purge_removes_expired_data(): void
    {
        $old = now()->subDays(100);
        $fresh = now()->subDays(10);
        foreach ([$old, $fresh] as $createdAt) {
            $challengeId = DB::table('competition_liveness_challenges')->insertGetId([
                'nonce_hash' => hash('sha256', (string) $createdAt),
                'siswa_nisn' => '9999000001',
                'session_hash' => hash('sha256', 'session'),
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

        $this->artisan('competition:purge-liveness-data', ['--days' => 90, '--dry-run' => true])
            ->expectsOutputToContain('1 challenge(s), 1 review item(s), 1 audit event(s)')
            ->assertExitCode(0);
        $this->assertDatabaseCount('competition_liveness_challenges', 2);

        $this->artisan('competition:purge-liveness-data', ['--days' => 90])
            ->assertExitCode(0);
        $this->assertDatabaseCount('competition_liveness_challenges', 1);
        $this->assertDatabaseCount('competition_verification_queue', 1);
        $this->assertDatabaseCount('competition_audit_logs', 2);
        $this->assertDatabaseHas('competition_audit_logs', ['event_type' => 'competition_data_purged']);
    }
}
