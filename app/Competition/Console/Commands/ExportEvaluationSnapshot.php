<?php

namespace App\Competition\Console\Commands;

use App\Competition\Models\EvaluationTrial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportEvaluationSnapshot extends Command
{
    protected $signature = 'competition:export-evaluation-snapshot
        {--directory= : Output directory (default: docs/evaluation)}';

    protected $description = 'Export anonymized manual evaluation CSV and methodology snapshot';

    private const HEADERS = ['trial_type', 'outcome', 'reason', 'tester_code', 'tested_at', 'notes'];

    public function handle(): int
    {
        $directory = (string) ($this->option('directory') ?: base_path('docs/evaluation'));
        File::ensureDirectoryExists($directory);

        $csvPath = $directory.DIRECTORY_SEPARATOR.'manual_trials.csv';
        $handle = fopen($csvPath, 'wb');
        if ($handle === false) {
            $this->error('The anonymized CSV snapshot could not be written.');

            return self::FAILURE;
        }

        fputcsv($handle, self::HEADERS, ',', '"', '');
        $trials = EvaluationTrial::query()
            ->orderBy('tested_at')
            ->orderBy('id')
            ->get(self::HEADERS);
        foreach ($trials as $trial) {
            fputcsv($handle, [
                $trial->trial_type,
                $trial->outcome,
                $trial->reason,
                $trial->tester_code,
                $trial->tested_at->format('Y-m-d'),
                $trial->notes,
            ], ',', '"', '');
        }
        fclose($handle);

        $types = $trials->countBy('trial_type');
        $firstDate = $trials->min('tested_at')?->format('Y-m-d') ?? 'none';
        $lastDate = $trials->max('tested_at')?->format('Y-m-d') ?? 'none';
        $typeCounts = collect(['genuine', 'print', 'screen'])
            ->map(static fn (string $type): string => '- '.$type.': '.($types[$type] ?? 0).' trial(s)')
            ->implode("\n");
        $emptyState = $trials->isEmpty()
            ? "\n\nNo manual trials have been recorded. FAR, FRR, and attack rejection rates are unavailable; no performance value is inferred."
            : '';

        $methodology = "# Manual AI evaluation snapshot\n\n"
            .'Generated: '.now()->toIso8601String()."\n"
            .'Total recorded trials (n): '.$trials->count()."\n"
            ."Test date range: {$firstDate} to {$lastDate}\n\n"
            ."## Trial counts\n{$typeCounts}\n\n"
            ."## Method and definitions\n"
            ."- genuine: consenting adult volunteer; print: printed photograph; screen: photograph displayed on a screen.\n"
            ."- FAR is accepted attack attempts / decided attack attempts; print and screen are reported separately.\n"
            ."- FRR is rejected genuine attempts / decided genuine attempts.\n"
            ."- Review outcomes are reported separately and excluded from each decided denominator (accepted + rejected).\n"
            ."- The CSV contains no face image, descriptor, NISN, or tester name. Tester identifiers must be anonymous codes (T1, T2, ...).\n"
            ."- Notes and reasons must be manually checked for names or other identifying details before import/export.\n"
            ."- Results are descriptive of the recorded convenience sample only, not a security guarantee or population estimate.\n"
            .$emptyState."\n";

        if (file_put_contents($directory.DIRECTORY_SEPARATOR.'METHODOLOGY.md', $methodology) === false) {
            $this->error('The methodology snapshot could not be written.');

            return self::FAILURE;
        }

        $this->info('Exported '.$trials->count().' anonymized trial(s) to '.$directory.'.');

        return self::SUCCESS;
    }
}
