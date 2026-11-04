<?php

namespace Tests\Feature;

use App\Competition\Services\TrustDecisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrustDecisionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('nisn');
            $table->date('tgl_presensi');
            $table->time('jam_in')->nullable();
        });
        Schema::create('school_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->timestamps();
        });
        DB::table('school_settings')->insert([
            'latitude' => -7.671325,
            'longitude' => 110.589607,
            'radius_meters' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        config([
            'competition.liveness_challenge.weights' => [
                'face' => 30,
                'gps' => 25,
                'consistency' => 15,
                'liveness' => 30,
            ],
            'competition.liveness_challenge.thresholds.accept' => 70,
            'competition.liveness_challenge.thresholds.review' => 40,
        ]);
    }

    public function test_high_score_is_accepted_only_with_matching_liveness_proof(): void
    {
        $valid = app(TrustDecisionService::class)->evaluate(
            $this->request(0.2, '-7.671325,110.589607'),
            '9999000001',
            'blink',
            'passed',
            ['delta' => 0.2, 'elapsed_ms' => 1200]
        );
        $unavailable = app(TrustDecisionService::class)->evaluate(
            $this->request(0.2, '-7.671325,110.589607'),
            '9999000001',
            'blink',
            'unavailable',
            []
        );
        $invalidProof = app(TrustDecisionService::class)->evaluate(
            $this->request(0.2, '-7.671325,110.589607'),
            '9999000001',
            'blink',
            'passed',
            ['delta' => 0.01, 'elapsed_ms' => 1200]
        );

        $this->assertSame(100, $valid['score']);
        $this->assertSame('accepted', $valid['verdict']);
        $this->assertSame(70, $unavailable['score']);
        $this->assertSame('review', $unavailable['verdict']);
        $this->assertSame('liveness_unavailable', $unavailable['reason_code']);
        $this->assertSame('rejected', $invalidProof['verdict']);
        $this->assertSame('liveness_failed', $invalidProof['reason_code']);
    }

    public function test_medium_and_low_scores_follow_configured_thresholds(): void
    {
        config([
            'competition.liveness_challenge.weights' => [
                'face' => 10,
                'gps' => 10,
                'consistency' => 10,
                'liveness' => 30,
            ],
        ]);
        $medium = app(TrustDecisionService::class)->evaluate(
            $this->request(0.2, '-7.671325,110.589607'),
            '9999000001',
            'turn_right',
            'passed',
            ['delta' => 0.2, 'elapsed_ms' => 1200]
        );

        config([
            'competition.liveness_challenge.weights' => [
                'face' => 10,
                'gps' => 0,
                'consistency' => 0,
                'liveness' => 10,
            ],
        ]);
        $low = app(TrustDecisionService::class)->evaluate(
            $this->request(0.2, '0,0'),
            '9999000001',
            'turn_right',
            'passed',
            ['delta' => 0.2, 'elapsed_ms' => 1200]
        );

        $this->assertSame(60, $medium['score']);
        $this->assertSame('review', $medium['verdict']);
        $this->assertSame(20, $low['score']);
        $this->assertSame('rejected', $low['verdict']);
    }

    public function test_score_at_accept_threshold_is_accepted_when_liveness_passes(): void
    {
        config([
            'competition.liveness_challenge.weights' => [
                'face' => 20,
                'gps' => 20,
                'consistency' => 10,
                'liveness' => 20,
            ],
            'competition.liveness_challenge.thresholds.accept' => 70,
        ]);
        $result = app(TrustDecisionService::class)->evaluate(
            $this->request(0.2, '-7.671325,110.589607'),
            '9999000001',
            'blink',
            'passed',
            ['delta' => 0.2, 'elapsed_ms' => 1200]
        );

        $this->assertSame(70, $result['score']);
        $this->assertSame('accepted', $result['verdict']);
    }

    public function test_unavailable_fallback_requires_minimum_face_and_gps_scores(): void
    {
        $faceTooLow = app(TrustDecisionService::class)->evaluate(
            $this->request(0.55, '-7.671325,110.589607'),
            '9999000001',
            'blink',
            'unavailable',
            []
        );
        $gpsTooLow = app(TrustDecisionService::class)->evaluate(
            $this->request(0.2, '0,0'),
            '9999000001',
            'blink',
            'unavailable',
            []
        );

        $this->assertSame('face_signal_too_low', $faceTooLow['reason_code']);
        $this->assertSame('gps_signal_too_low', $gpsTooLow['reason_code']);
        $this->assertSame('rejected', $faceTooLow['verdict']);
        $this->assertSame('rejected', $gpsTooLow['verdict']);
    }

    public function test_unavailable_fallback_below_review_score_is_rejected(): void
    {
        config([
            'competition.liveness_challenge.weights' => [
                'face' => 18,
                'gps' => 15,
                'consistency' => 0,
                'liveness' => 30,
            ],
        ]);
        $result = app(TrustDecisionService::class)->evaluate(
            $this->request(0.2, '-7.671325,110.589607'),
            '9999000001',
            'blink',
            'unavailable',
            []
        );

        $this->assertSame(33, $result['score']);
        $this->assertSame('score_below_review', $result['reason_code']);
        $this->assertSame('rejected', $result['verdict']);
    }

    private function request(float $confidence, string $location): Request
    {
        return Request::create('/presensi/face/store', 'POST', [
            'confidence' => $confidence,
            'lokasi' => $location,
        ]);
    }
}
