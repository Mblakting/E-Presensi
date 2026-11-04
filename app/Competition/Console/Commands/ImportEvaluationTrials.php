<?php

namespace App\Competition\Console\Commands;

use App\Competition\Models\AuditLog;
use App\Competition\Models\EvaluationTrial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportEvaluationTrials extends Command
{
    protected $signature = 'competition:import-evaluation-trials {file : CSV with the documented trial columns}';

    protected $description = 'Validate and import anonymized manual AI evaluation trials from CSV';

    private const HEADERS = ['trial_type', 'outcome', 'reason', 'tester_code', 'tested_at', 'notes'];

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            return $this->rejectImport('file_unavailable', 'The CSV file does not exist or is not readable.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return $this->rejectImport('file_open_failed', 'The CSV file could not be opened.');
        }

        $headers = fgetcsv($handle, null, ',', '"', '');
        if (! is_array($headers)) {
            fclose($handle);

            return $this->rejectImport('missing_header', 'The CSV file is empty or has no header row.');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        if ($headers !== self::HEADERS) {
            fclose($handle);

            return $this->rejectImport('invalid_headers', 'CSV headers must be exactly: '.implode(',', self::HEADERS));
        }

        $trials = [];
        $errors = [];
        $validRowCount = 0;
        $line = 1;
        while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $line++;
            if ($values === [null]) {
                continue;
            }
            if (count($values) !== count(self::HEADERS)) {
                $errors[] = $line;

                continue;
            }

            $row = array_combine(self::HEADERS, array_map(
                static fn (mixed $value): string => trim((string) $value),
                $values
            ));
            $trial = $this->validatedTrial($row);
            if ($trial === null) {
                $errors[] = $line;

                continue;
            }

            $validRowCount++;
            if ($validRowCount > 10000) {
                fclose($handle);

                return $this->rejectImport('too_many_rows', 'A single CSV import is limited to 10,000 rows.', [
                    'valid_rows' => $validRowCount,
                ]);
            }

            $trial['fingerprint'] = EvaluationTrial::fingerprintFor($trial);
            $trials[$trial['fingerprint']] = $trial;
        }
        fclose($handle);

        if ($errors !== []) {
            return $this->rejectImport(
                'invalid_rows',
                'CSV validation failed on line(s): '.implode(', ', $errors).'. No rows were imported.',
                ['invalid_rows' => count($errors)]
            );
        }

        $existing = collect(array_keys($trials))
            ->chunk(500)
            ->flatMap(static fn ($fingerprints) => DB::table('competition_evaluation_trials')
                ->whereIn('fingerprint', $fingerprints)
                ->pluck('fingerprint'))
            ->flip();
        $newTrials = collect($trials)
            ->reject(static fn (array $trial): bool => isset($existing[$trial['fingerprint']]))
            ->values()
            ->all();
        $duplicateCount = $validRowCount - count($newTrials);

        DB::transaction(function () use ($newTrials, $duplicateCount): void {
            foreach ($newTrials as $trial) {
                EvaluationTrial::query()->create($trial);
            }
            AuditLog::record(
                'evaluation_trials_imported',
                'system',
                null,
                null,
                ['imported' => count($newTrials), 'duplicate_rows_skipped' => $duplicateCount]
            );
        });

        $this->info('Imported '.count($newTrials).' trial(s); '.$duplicateCount.' exact duplicate row(s) skipped.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int|string>  $metadata
     */
    private function rejectImport(string $reasonCode, string $message, array $metadata = []): int
    {
        AuditLog::record(
            'evaluation_trials_import_rejected',
            'system',
            null,
            null,
            ['reason_code' => $reasonCode, ...$metadata]
        );
        $this->error($message);

        return self::FAILURE;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{trial_type: string, outcome: string, reason: string, tester_code: string, tested_at: string, notes: ?string}|null
     */
    private function validatedTrial(array $row): ?array
    {
        if (! in_array($row['trial_type'], ['genuine', 'print', 'screen'], true)
            || ! in_array($row['outcome'], ['accepted', 'rejected', 'review'], true)
            || ! preg_match('/\AT[1-9][0-9]{0,2}\z/', $row['tester_code'])
            || $row['reason'] === ''
            || strlen($row['reason']) > 500
            || strlen($row['notes']) > 500
            || $this->containsLikelyIdentifier($row['reason'])
            || $this->containsLikelyIdentifier($row['notes'])) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $row['tested_at']);
        if ($date === false || $date->format('Y-m-d') !== $row['tested_at']) {
            return null;
        }

        return [
            'trial_type' => $row['trial_type'],
            'outcome' => $row['outcome'],
            'reason' => $row['reason'],
            'tester_code' => $row['tester_code'],
            'tested_at' => $row['tested_at'],
            'notes' => $row['notes'] === '' ? null : $row['notes'],
        ];
    }

    private function containsLikelyIdentifier(string $value): bool
    {
        return preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}|\b\d{7,}\b|(?<!\d)(?:\+?62|0)\d{8,14}(?!\d)/u', $value) === 1;
    }
}
