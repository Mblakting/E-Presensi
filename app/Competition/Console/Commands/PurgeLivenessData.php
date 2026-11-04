<?php

namespace App\Competition\Console\Commands;

use App\Competition\Models\AuditLog;
use App\Competition\Models\LivenessChallenge;
use App\Competition\Models\VerificationQueueItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeLivenessData extends Command
{
    protected $signature = 'competition:purge-liveness-data
        {--days= : Override liveness and review retention days}
        {--audit-days= : Override audit retention days}
        {--consent-days= : Override superseded consent history retention days}
        {--trials-days= : Override manual evaluation trial retention days}
        {--dry-run : Show records eligible for deletion without deleting them}';

    protected $description = 'Manually purge expired competition data using configured retention periods';

    public function handle(): int
    {
        $days = [
            'liveness' => $this->retentionDays('days', 'competition.retention.liveness_days'),
            'audit' => $this->retentionDays('audit-days', 'competition.retention.audit_days'),
            'consent' => $this->retentionDays('consent-days', 'competition.retention.consent_history_days'),
            'trials' => $this->retentionDays('trials-days', 'competition.evaluation.retention_days'),
        ];
        if (in_array(null, $days, true)) {
            return self::FAILURE;
        }

        $livenessCutoff = now()->subDays($days['liveness']);
        $auditCutoff = now()->subDays($days['audit']);
        $consentCutoff = now()->subDays($days['consent']);
        $trialsCutoff = now()->subDays($days['trials'])->toDateString();

        $challenges = Schema::hasTable('competition_liveness_challenges')
            ? LivenessChallenge::query()->where('created_at', '<', $livenessCutoff)
            : null;
        $queueItems = Schema::hasTable('competition_verification_queue')
            ? VerificationQueueItem::query()->where('created_at', '<', $livenessCutoff)
            : null;
        $auditEvents = Schema::hasTable('competition_audit_logs')
            ? DB::table('competition_audit_logs')->where('created_at', '<', $auditCutoff)
            : null;
        $consentHistory = null;
        if (Schema::hasTable('competition_consents')) {
            $latestConsentIds = DB::table('competition_consents')
                ->selectRaw('MAX(id)')
                ->groupBy('siswa_nisn');
            $consentHistory = DB::table('competition_consents')
                ->where('recorded_at', '<', $consentCutoff)
                ->whereNotIn('id', $latestConsentIds);
        }
        $trials = Schema::hasTable('competition_evaluation_trials')
            ? DB::table('competition_evaluation_trials')->where('tested_at', '<', $trialsCutoff)
            : null;

        $counts = [
            'challenges' => $challenges?->count() ?? 0,
            'queue' => $queueItems?->count() ?? 0,
            'audit' => $auditEvents?->count() ?? 0,
            'consent_history' => $consentHistory?->count() ?? 0,
            'trials' => $trials?->count() ?? 0,
        ];

        if ($this->option('dry-run')) {
            $this->info('Dry run: '.$counts['challenges'].' challenge(s), '.$counts['queue'].' review item(s), '.$counts['audit'].' audit event(s), '.$counts['consent_history'].' superseded consent record(s), and '.$counts['trials'].' evaluation trial(s) would be deleted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($challenges, $queueItems, $auditEvents, $consentHistory, $trials, $counts, $days): void {
            $queueItems?->delete();
            $challenges?->delete();
            $auditEvents?->delete();
            $consentHistory?->delete();
            $trials?->delete();
            if (Schema::hasTable('competition_audit_logs')) {
                AuditLog::record(
                    'competition_data_purged',
                    'system',
                    null,
                    null,
                    ['deleted' => $counts, 'retention_days' => $days]
                );
            }
        });

        $this->info('Deleted '.$counts['challenges'].' challenge(s), '.$counts['queue'].' review item(s), '.$counts['audit'].' audit event(s), '.$counts['consent_history'].' superseded consent record(s), and '.$counts['trials'].' evaluation trial(s).');

        return self::SUCCESS;
    }

    private function retentionDays(string $option, string $configKey): ?int
    {
        $value = $this->option($option) ?? config($configKey);
        $days = filter_var($value, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1) {
            $this->error("The --{$option} value or {$configKey} must be a positive integer.");

            return null;
        }

        return $days;
    }
}
