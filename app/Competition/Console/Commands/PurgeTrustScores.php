<?php

namespace App\Competition\Console\Commands;

use App\Competition\Models\AuditLog;
use App\Competition\Models\TrustScore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeTrustScores extends Command
{
    protected $signature = 'competition:purge-trust-scores
        {--days= : Override trust score retention days}
        {--dry-run : Show how many records would be deleted without deleting them}';

    protected $description = 'Purge expired competition trust scores';

    public function handle(): int
    {
        $value = $this->option('days') ?? config('competition.retention.trust_scores_days', 90);
        $days = filter_var($value, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1) {
            $this->error('The --days option must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = TrustScore::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} trust score(s) older than {$days} days would be deleted.");

            return self::SUCCESS;
        }

        $deleted = 0;
        DB::transaction(function () use ($query, $days, &$deleted): void {
            $deleted = $query->delete();
            if (Schema::hasTable('competition_audit_logs')) {
                AuditLog::record('trust_scores_purged', 'system', null, null, [
                    'deleted' => $deleted,
                    'retention_days' => $days,
                ]);
            }
        });
        $this->info("Deleted {$deleted} trust score(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
