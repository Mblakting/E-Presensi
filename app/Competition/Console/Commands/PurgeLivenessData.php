<?php

namespace App\Competition\Console\Commands;

use App\Competition\Models\LivenessChallenge;
use App\Competition\Models\VerificationQueueItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeLivenessData extends Command
{
    protected $signature = 'competition:purge-liveness-data
        {--days=90 : Delete liveness and review records older than this number of days}
        {--dry-run : Show records eligible for deletion without deleting them}';

    protected $description = 'Purge expired competition liveness challenges, review items, and related audit events';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 1) {
            $this->error('The --days option must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $challenges = LivenessChallenge::query()->where('created_at', '<', $cutoff);
        $queueItems = VerificationQueueItem::query()->where('created_at', '<', $cutoff);
        $auditEvents = DB::table('competition_audit_logs')
            ->whereIn('event_type', ['verification_queued', 'verification_review_decision'])
            ->where('created_at', '<', $cutoff);
        $challengeCount = (clone $challenges)->count();
        $queueCount = (clone $queueItems)->count();
        $auditCount = (clone $auditEvents)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$challengeCount} challenge(s), {$queueCount} review item(s), and {$auditCount} related audit event(s) older than {$days} days would be deleted.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($challenges, $queueItems, $auditEvents): void {
            $queueItems->delete();
            $challenges->delete();
            $auditEvents->delete();
        });

        $this->info("Deleted {$challengeCount} challenge(s), {$queueCount} review item(s), and {$auditCount} related audit event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
