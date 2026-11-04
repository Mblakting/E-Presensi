<?php

namespace App\Competition\Console\Commands;

use App\Competition\Models\AuditLog;
use App\Competition\Models\LivenessChallenge;
use App\Competition\Models\ManualVerification;
use App\Competition\Models\VerificationQueueItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PurgeLivenessData extends Command
{
    protected $signature = 'competition:purge-liveness-data
        {--days= : Override liveness and review retention days}
        {--audit-days= : Override audit retention days}
        {--consent-days= : Override superseded consent history retention days}
        {--trials-days= : Override manual evaluation trial retention days}
        {--manual-days= : Override manual verification retention days}
        {--dry-run : Show records eligible for deletion without deleting them}';

    protected $description = 'Manually purge expired competition data using configured retention periods';

    public function handle(): int
    {
        $days = [
            'liveness' => $this->retentionDays('days', 'competition.retention.liveness_days'),
            'audit' => $this->retentionDays('audit-days', 'competition.retention.audit_days'),
            'consent' => $this->retentionDays('consent-days', 'competition.retention.consent_history_days'),
            'trials' => $this->retentionDays('trials-days', 'competition.evaluation.retention_days'),
            'manual' => $this->retentionDays('manual-days', 'competition.manual_verification.retention_days'),
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
        if ($challenges && Schema::hasTable('competition_manual_verifications')) {
            $retainedManualChallengeIds = ManualVerification::query()
                ->select('challenge_id')
                ->where('attempted_at', '>=', now()->subDays($days['manual']));
            $challenges->whereNotIn('id', $retainedManualChallengeIds);
        }
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
        $manualVerifications = Schema::hasTable('competition_manual_verifications')
            ? ManualVerification::query()->where('attempted_at', '<', now()->subDays($days['manual']))
            : null;
        $manualSnapshotPaths = $manualVerifications
            ? (clone $manualVerifications)->pluck('foto_tangkapan')->all()
            : [];

        $counts = [
            'challenges' => $challenges?->count() ?? 0,
            'queue' => $queueItems?->count() ?? 0,
            'audit' => $auditEvents?->count() ?? 0,
            'consent_history' => $consentHistory?->count() ?? 0,
            'trials' => $trials?->count() ?? 0,
            'manual_verifications' => $manualVerifications?->count() ?? 0,
        ];

        if ($this->option('dry-run')) {
            $this->info('Dry run: '.$counts['challenges'].' challenge(s), '.$counts['queue'].' review item(s), '.$counts['audit'].' audit event(s), '.$counts['consent_history'].' superseded consent record(s), '.$counts['trials'].' evaluation trial(s), and '.$counts['manual_verifications'].' manual verification(s) would be deleted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($challenges, $queueItems, $auditEvents, $consentHistory, $trials, $manualVerifications, $counts, $days): void {
            $queueItems?->delete();
            $manualVerifications?->delete();
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
        if (! $this->deletePrivateSnapshots($manualSnapshotPaths)) {
            return self::FAILURE;
        }

        $this->info('Deleted '.$counts['challenges'].' challenge(s), '.$counts['queue'].' review item(s), '.$counts['audit'].' audit event(s), '.$counts['consent_history'].' superseded consent record(s), '.$counts['trials'].' evaluation trial(s), and '.$counts['manual_verifications'].' manual verification(s).');

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

    /**
     * @param  list<mixed>  $paths
     */
    private function deletePrivateSnapshots(array $paths): bool
    {
        $disk = Storage::disk('local');
        $deleted = true;
        foreach (array_unique($paths) as $path) {
            if (! is_string($path) || preg_match('/\Acompetition\/manual-verifications\/[0-9]+\.jpg\z/', $path) !== 1) {
                continue;
            }
            if ($disk->exists($path) && ! $disk->delete($path)) {
                $this->error('Failed to delete expired private snapshot: '.$path);
                $deleted = false;
            }
        }

        return $deleted;
    }
}
