<?php

namespace App\Competition\Console\Commands;

use App\Competition\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EraseSyntheticCompetitionSubject extends Command
{
    protected $signature = 'competition:erase-synthetic-subject
        {nisn : Synthetic competition NISN starting with 9999}
        {--dry-run : Preview matching competition rows without deleting}
        {--confirm : Confirm the irreversible competition-table deletion}';

    protected $description = 'Erase competition-only records for one synthetic NISN';

    /**
     * @var array<string, string>
     */
    private const TABLES = [
        'competition_manual_verifications' => 'siswa_nisn',
        'competition_trust_scores' => 'nisn',
        'competition_liveness_challenges' => 'siswa_nisn',
        'competition_verification_queue' => 'siswa_nisn',
        'competition_consents' => 'siswa_nisn',
    ];

    public function handle(): int
    {
        if (! (bool) config('competition.data_erasure', false)
            || ! (bool) config('competition.consent_gate', false)) {
            $this->error('Synthetic subject erasure is disabled; enable both competition data erasure and the consent gate on the competition instance.');

            return self::FAILURE;
        }

        $nisn = (string) $this->argument('nisn');
        if (! preg_match('/\A9999[0-9]+\z/', $nisn)) {
            $this->error('This command accepts only synthetic NISN values beginning with 9999.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('competition_audit_logs')) {
            $this->error('The competition audit table is required; no data was erased.');

            return self::FAILURE;
        }

        $queries = [];
        foreach (self::TABLES as $table => $column) {
            if (Schema::hasTable($table)) {
                $queries[$table] = DB::table($table)->where($column, $nisn);
            }
        }
        if (Schema::hasColumn('competition_audit_logs', 'subject_nisn')) {
            $queries['competition_audit_logs'] = DB::table('competition_audit_logs')->where('subject_nisn', $nisn);
        }

        $counts = [];
        $snapshotPaths = [];
        foreach ($queries as $table => $query) {
            $counts[$table] = (clone $query)->count();
            if ($table === 'competition_manual_verifications') {
                $snapshotPaths = (clone $query)->pluck('foto_tangkapan')->all();
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: '.$this->formatCounts($counts).' would be erased. Client tables and evaluation trials are not included.');

            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $this->error('Deletion is irreversible; rerun with --dry-run, review the counts, then pass --confirm.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($queries, $counts, $nisn): void {
            foreach ($queries as $query) {
                $query->delete();
            }

            AuditLog::record(
                'competition_subject_erased',
                'system',
                null,
                $nisn,
                ['deleted' => $counts],
                true
            );
        });
        if (! $this->deletePrivateSnapshots($snapshotPaths)) {
            return self::FAILURE;
        }

        $this->info('Erased '.$this->formatCounts($counts).'. The latest consent record for this synthetic NISN was also erased; client tables were not changed.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function formatCounts(array $counts): string
    {
        return collect($counts)
            ->map(static fn (int $count, string $table): string => "{$table}: {$count}")
            ->implode(', ');
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
                $this->error('Failed to delete private snapshot: '.$path);
                $deleted = false;
            }
        }

        return $deleted;
    }
}
