<?php

namespace App\Competition\Console\Commands;

use App\Competition\Models\TrustScore;
use Illuminate\Console\Command;

class PurgeTrustScores extends Command
{
    protected $signature = 'competition:purge-trust-scores
        {--days=90 : Delete trust scores older than this number of days}
        {--dry-run : Show how many records would be deleted without deleting them}';

    protected $description = 'Purge expired competition trust scores';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
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

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} trust score(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
