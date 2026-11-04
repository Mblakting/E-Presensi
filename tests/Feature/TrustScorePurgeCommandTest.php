<?php

namespace Tests\Feature;

use App\Competition\Models\TrustScore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrustScorePurgeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $migration = require base_path('database/migrations/2026_digifest_001_create_competition_trust_scores_table.php');
        $migration->up();
    }

    public function test_dry_run_reports_expired_rows_without_deleting_them(): void
    {
        $this->createTrustScore(now()->subDays(91));
        $this->createTrustScore(now()->subDays(30));

        $this->artisan('competition:purge-trust-scores', ['--days' => 90, '--dry-run' => true])
            ->expectsOutputToContain('1 trust score(s)')
            ->assertExitCode(0);

        $this->assertSame(2, TrustScore::query()->count());
    }

    public function test_command_deletes_only_rows_older_than_retention_days(): void
    {
        $this->createTrustScore(now()->subDays(91));
        $this->createTrustScore(now()->subDays(30));

        $this->artisan('competition:purge-trust-scores', ['--days' => 90])
            ->expectsOutputToContain('Deleted 1 trust score(s)')
            ->assertExitCode(0);

        $this->assertSame(1, TrustScore::query()->count());
    }

    public function test_command_rejects_non_positive_retention_days(): void
    {
        $this->artisan('competition:purge-trust-scores', ['--days' => 0])
            ->expectsOutputToContain('positive integer')
            ->assertExitCode(1);
    }

    private function createTrustScore(\Carbon\CarbonInterface $createdAt): void
    {
        DB::table('competition_trust_scores')->insert([
            'nisn' => '9999000001',
            'score' => 75,
            'absen_type' => 'in',
            'method' => 'gps',
            'verdict' => 'accepted',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
