<?php

namespace App\Competition\Services;

use App\Competition\Models\EvaluationTrial;
use Illuminate\Support\Facades\DB;

class EvaluationReportService
{
    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $verdicts = DB::table('competition_liveness_challenges')
            ->select('verdict', DB::raw('COUNT(*) as total'))
            ->groupBy('verdict')
            ->pluck('total', 'verdict')
            ->all();
        $liveness = DB::table('competition_liveness_challenges')
            ->select('liveness_status', DB::raw('COUNT(*) as total'))
            ->groupBy('liveness_status')
            ->pluck('total', 'liveness_status')
            ->all();
        $rejectionReasons = DB::table('competition_liveness_challenges')
            ->select('reason_code', 'reason', DB::raw('COUNT(*) as total'))
            ->where('verdict', 'rejected')
            ->whereNotNull('reason_code')
            ->groupBy('reason_code', 'reason')
            ->orderByDesc('total')
            ->get();

        $byType = [];
        $trials = EvaluationTrial::query()
            ->select('trial_type', 'outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('trial_type', 'outcome')
            ->get();

        foreach (['genuine', 'print', 'screen'] as $type) {
            $counts = ['accepted' => 0, 'rejected' => 0, 'review' => 0];
            foreach ($trials->where('trial_type', $type) as $trial) {
                if (array_key_exists($trial->outcome, $counts)) {
                    $counts[$trial->outcome] = (int) $trial->total;
                }
            }

            $total = array_sum($counts);
            $decided = $counts['accepted'] + $counts['rejected'];
            $rate = static fn (int $value): ?float => $decided === 0
                ? null
                : round($value / $decided * 100, 1);

            $byType[$type] = [
                ...$counts,
                'n' => $total,
                'n_decided' => $decided,
                'frr' => $type === 'genuine' ? $rate($counts['rejected']) : null,
                'far' => $type === 'genuine' ? null : $rate($counts['accepted']),
                'attack_rejection_rate' => $type === 'genuine' ? null : $rate($counts['rejected']),
            ];
        }

        return [
            'verdicts' => $verdicts,
            'liveness' => $liveness,
            'rejectionReasons' => $rejectionReasons,
            'trials' => $byType,
            'trialCount' => array_sum(array_column($byType, 'n')),
            'smallSampleThreshold' => (int) config('competition.evaluation.small_sample_threshold', 20),
        ];
    }
}
