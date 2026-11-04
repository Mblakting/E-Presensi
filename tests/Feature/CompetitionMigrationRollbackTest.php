<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompetitionMigrationRollbackTest extends TestCase
{
    public function test_phase_three_migrations_create_and_drop_only_their_competition_tables(): void
    {
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
        });
        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('nisn');
        });
        $challengeMigration = require base_path('database/migrations/2026_digifest_003_create_competition_liveness_challenges_table.php');
        $queueMigration = require base_path('database/migrations/2026_digifest_004_create_competition_verification_queue_table.php');

        $challengeMigration->up();
        $queueMigration->up();
        $this->assertTrue(Schema::hasTable('competition_liveness_challenges'));
        $this->assertTrue(Schema::hasTable('competition_verification_queue'));

        $queueMigration->down();
        $challengeMigration->down();

        $this->assertFalse(Schema::hasTable('competition_verification_queue'));
        $this->assertFalse(Schema::hasTable('competition_liveness_challenges'));
        $this->assertTrue(Schema::hasTable('siswa'));
        $this->assertTrue(Schema::hasTable('presensi'));
    }

    public function test_phase_four_consent_migration_rolls_back_without_touching_client_tables(): void
    {
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
        });
        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('nisn');
        });
        $migration = require base_path('database/migrations/2026_digifest_005_create_competition_consents_table.php');

        $migration->up();
        $this->assertTrue(Schema::hasTable('competition_consents'));
        $this->assertSame(
            ['id', 'siswa_nisn', 'status', 'text_version', 'text_hash', 'actor_type', 'actor_id', 'authority_basis', 'recorded_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('competition_consents')
        );

        $migration->down();

        $this->assertFalse(Schema::hasTable('competition_consents'));
        $this->assertTrue(Schema::hasTable('siswa'));
        $this->assertTrue(Schema::hasTable('presensi'));
    }

    public function test_phase_four_evaluation_migration_rolls_back_without_touching_client_tables(): void
    {
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
        });
        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('nisn');
        });
        $migration = require base_path('database/migrations/2026_digifest_006_create_competition_evaluation_trials_table.php');

        $migration->up();
        $this->assertTrue(Schema::hasTable('competition_evaluation_trials'));
        $this->assertSame(
            ['id', 'trial_type', 'outcome', 'reason', 'tester_code', 'tested_at', 'notes', 'fingerprint', 'created_at', 'updated_at'],
            Schema::getColumnListing('competition_evaluation_trials')
        );

        $migration->down();

        $this->assertFalse(Schema::hasTable('competition_evaluation_trials'));
        $this->assertTrue(Schema::hasTable('siswa'));
        $this->assertTrue(Schema::hasTable('presensi'));
    }

    public function test_phase_three_b_manual_verification_migration_rolls_back_without_touching_client_tables(): void
    {
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
        });
        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('nisn');
        });
        foreach ([
            '2026_digifest_001_create_competition_trust_scores_table.php',
            '2026_digifest_003_create_competition_liveness_challenges_table.php',
        ] as $migrationFile) {
            $dependency = require base_path('database/migrations/'.$migrationFile);
            $dependency->up();
        }
        $migration = require base_path('database/migrations/2026_digifest_007_create_competition_manual_verifications_table.php');

        $migration->up();
        $this->assertSame(
            [
                'id', 'challenge_id', 'trust_score_id', 'siswa_nisn', 'score', 'reason_code',
                'reason', 'absen_type', 'attendance_date', 'attendance_time', 'lokasi',
                'confidence', 'foto_tangkapan', 'status', 'admin_id', 'catatan',
                'attempted_at', 'reviewed_at', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('competition_manual_verifications')
        );

        $migration->down();

        $this->assertFalse(Schema::hasTable('competition_manual_verifications'));
        $this->assertTrue(Schema::hasTable('siswa'));
        $this->assertTrue(Schema::hasTable('presensi'));
    }
}
