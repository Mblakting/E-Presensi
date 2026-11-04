<?php

namespace App\Competition\Http;

use App\Competition\Models\ConsentRecord;
use App\Competition\Models\ManualVerification;
use App\Competition\Models\VerificationQueueItem;
use App\Competition\Services\ConsentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CompetitionNavigationController
{
    public function index(ConsentService $consents): View|\Illuminate\Http\RedirectResponse
    {
        if (Auth::guard('user')->check()) {
            return view('competition::hub.index', [
                'audience' => 'admin',
                'featureCards' => $this->adminFeatureCards($consents),
            ]);
        }

        if (Auth::guard('siswa')->check()) {
            $nisn = (string) Auth::guard('siswa')->id();
            $latestConsent = Schema::hasTable('competition_consents')
                ? $consents->latest($nisn)
                : null;
            $consentStatus = match (true) {
                $latestConsent === null => 'Belum ada persetujuan',
                $consents->isGranted($nisn) => 'Persetujuan aktif',
                $latestConsent->status === 'withdrawn' => 'Persetujuan ditarik',
                default => 'Persetujuan perlu diperbarui',
            };

            return view('competition::hub.index', [
                'audience' => 'student',
                'consentStatus' => $consentStatus,
                'studentConsentUrl' => Route::has('competition.student-consents.index')
                    ? route('competition.student-consents.index')
                    : null,
                'faceAttendanceUrl' => Route::has('presensi.face') ? route('presensi.face') : url('/presensi/face'),
            ]);
        }

        return redirect()->route('competition.guide');
    }

    public function guide(): View
    {
        return view('competition::hub.guide');
    }

    /**
     * @return list<array{title: string, description: string, flag: string, enabled: bool, url: ?string, count_label: string, count: ?int}>
     */
    private function adminFeatureCards(ConsentService $consents): array
    {
        $enforce = (bool) config('competition.trust_engine_enforce', false);
        $evaluation = (bool) config('competition.ai_evaluation_dashboard', false);
        $audit = (bool) config('competition.audit_viewer', false);
        $consent = (bool) config('competition.consent_gate', false);

        $pendingReviews = 0;
        if ($enforce && Schema::hasTable('competition_verification_queue')) {
            $pendingReviews += VerificationQueueItem::query()->where('status', 'pending')->count();
        }
        if ($enforce && Schema::hasTable('competition_manual_verifications')) {
            $pendingReviews += ManualVerification::query()->where('status', 'pending')->count();
        }

        $trialCount = $evaluation && Schema::hasTable('competition_evaluation_trials')
            ? DB::table('competition_evaluation_trials')->count()
            : null;
        $auditCount = $audit && Schema::hasTable('competition_audit_logs')
            ? DB::table('competition_audit_logs')->count()
            : null;
        $activeConsentCount = $consent && Schema::hasTable('competition_consents')
            ? count($consents->allGrantedNisns())
            : null;

        return [
            $this->featureCard(
                'Dashboard Evaluasi AI',
                'Lihat ringkasan hasil challenge dan uji manual yang benar-benar tercatat.',
                'FEATURE_AI_EVALUATION_DASHBOARD',
                $evaluation,
                'competition.evaluation.index',
                'Jumlah uji tercatat',
                $trialCount
            ),
            $this->featureCard(
                'Antrean Review',
                'Tinjau percobaan presensi yang memerlukan keputusan manusia.',
                'FEATURE_TRUST_ENGINE_ENFORCE',
                $enforce,
                'competition.reviews.index',
                'Item menunggu review',
                $enforce ? $pendingReviews : null
            ),
            $this->featureCard(
                'Log Audit',
                'Baca jejak akses dan perubahan fitur kompetisi.',
                'FEATURE_COMPETITION_AUDIT_VIEWER',
                $audit,
                'competition.audit.index',
                'Event tercatat',
                $auditCount
            ),
            $this->featureCard(
                'Persetujuan',
                'Kelola pencatatan persetujuan sesuai teks dan proses yang telah ditinjau.',
                'FEATURE_COMPETITION_CONSENT',
                $consent,
                'competition.consents.index',
                'Persetujuan aktif',
                $activeConsentCount
            ),
        ];
    }

    /**
     * @return array{title: string, description: string, flag: string, enabled: bool, url: ?string, count_label: string, count: ?int}
     */
    private function featureCard(
        string $title,
        string $description,
        string $flag,
        bool $enabled,
        string $routeName,
        string $countLabel,
        ?int $count
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'flag' => $flag,
            'enabled' => $enabled,
            'url' => $enabled && Route::has($routeName) ? route($routeName) : null,
            'count_label' => $countLabel,
            'count' => $count,
        ];
    }
}
