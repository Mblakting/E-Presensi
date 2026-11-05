<?php

namespace App\Competition\Http;

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
                'consentGateEnabled' => (bool) config('competition.consent_gate', false),
                'consentTextApproved' => (bool) config('competition.consent.text_approved', false),
                'demoFlow' => $this->demoFlow(),
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
                'consentGateEnabled' => (bool) config('competition.consent_gate', false),
                'consentTextApproved' => (bool) config('competition.consent.text_approved', false),
                'studentConsentUrl' => Route::has('competition.student-consents.index')
                    ? route('competition.student-consents.index')
                    : null,
                'faceAttendanceUrl' => Route::has('presensi.face') ? route('presensi.face') : url('/presensi/face'),
                'demoFlow' => $this->demoFlow(),
            ]);
        }

        return redirect()->route('competition.guide');
    }

    public function guide(): View
    {
        return view('competition::hub.guide');
    }

    /**
     * @return list<array{label: string, url: ?string, reason: ?string}>
     */
    private function demoFlow(): array
    {
        return [
            ['label' => 'Masuk ke aplikasi', 'url' => route('login'), 'reason' => null],
            ['label' => 'Coba presensi wajah (siswa)', 'url' => Route::has('presensi.face') ? route('presensi.face') : null, 'reason' => 'Rute presensi wajah belum tersedia.'],
            ['label' => 'Tinjau antrean review (admin)', 'url' => Route::has('competition.reviews.index') ? route('competition.reviews.index') : null, 'reason' => 'Aktifkan FEATURE_TRUST_ENGINE_ENFORCE untuk membuka antrean.'],
            ['label' => 'Lihat Evaluasi AI (admin)', 'url' => Route::has('competition.evaluation.index') ? route('competition.evaluation.index') : null, 'reason' => 'Aktifkan FEATURE_AI_EVALUATION_DASHBOARD untuk membuka evaluasi.'],
            ['label' => 'Baca Log audit (admin)', 'url' => Route::has('competition.audit.index') ? route('competition.audit.index') : null, 'reason' => 'Aktifkan FEATURE_COMPETITION_AUDIT_VIEWER untuk membuka log audit.'],
            ['label' => 'Tinjau Persetujuan (admin)', 'url' => Route::has('competition.consents.index') ? route('competition.consents.index') : null, 'reason' => 'Aktifkan FEATURE_COMPETITION_CONSENT setelah teks ditinjau.'],
        ];
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
            'disabled_reason' => $this->disabledReason($flag, $enabled, $routeName),
            'count_label' => $countLabel,
            'count' => $count,
        ];
    }

    private function disabledReason(string $flag, bool $enabled, string $routeName): string
    {
        if (! $enabled) {
            return $flag === 'FEATURE_COMPETITION_CONSENT'
                ? 'Aktifkan hanya setelah teks dan proses persetujuan ditinjau.'
                : "Untuk membuka, atur {$flag}=true pada environment instance kompetisi.";
        }

        if (! Route::has($routeName)) {
            return 'Rute belum terdaftar. Periksa konfigurasi dan flag fitur.';
        }

        return 'Fitur belum tersedia.';
    }
}
