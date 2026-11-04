<?php

namespace Tests\Feature;

use App\Competition\Services\TrustEngineService;
use App\Competition\Models\TrustScore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrustEngineServiceTest extends TestCase
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

        $migration = require base_path('database/migrations/2026_digifest_001_create_competition_trust_scores_table.php');
        $migration->up();
    }

    public function test_gps_attendance_uses_neutral_face_score_and_accepts_valid_signals(): void
    {
        $result = app(TrustEngineService::class)->evaluate(
            nisn: '99990001',
            absenType: 'in',
            method: 'gps',
            faceDistance: null,
            lokasi: '-8.65,115.21',
            tglPresensi: '2026-11-04',
            jamAbsen: '07:00:00',
            gpsAccuracy: 8.5,
        );

        $this->assertSame(75, $result['score']);
        $this->assertSame('accepted', $result['verdict']);
        $this->assertSame([
            'face' => 20,
            'gps' => 40,
            'consistency' => 15,
        ], $result['components']);

        $signals = TrustScore::query()->firstOrFail()->raw_signals;
        $this->assertSame([
            'geofence_distance_meters',
            'gps_accuracy_meters',
            'time_difference_minutes',
        ], array_keys($signals));
        $this->assertGreaterThan(0, $signals['geofence_distance_meters']);
        $this->assertSame(8.5, $signals['gps_accuracy_meters']);
        $this->assertNull($signals['time_difference_minutes']);
        $this->assertArrayNotHasKey('lokasi', $signals);
        $this->assertArrayNotHasKey('jam_absen', $signals);
    }

    public function test_perfect_face_distance_with_valid_gps_scores_as_accepted(): void
    {
        $result = app(TrustEngineService::class)->evaluate(
            nisn: '99990002',
            absenType: 'in',
            method: 'face',
            faceDistance: 0.20,
            lokasi: '-8.65,115.21',
            tglPresensi: '2026-11-04',
            jamAbsen: '07:00:00',
        );

        $this->assertSame(95, $result['score']);
        $this->assertSame('accepted', $result['verdict']);
        $this->assertSame([
            'face' => 40,
            'gps' => 40,
            'consistency' => 15,
        ], $result['components']);
    }

    public function test_time_difference_is_saved_as_a_derived_value(): void
    {
        foreach (['2026-11-01', '2026-11-02', '2026-11-03'] as $date) {
            \Illuminate\Support\Facades\DB::table('presensi')->insert([
                'nisn' => '9999000004',
                'tgl_presensi' => $date,
                'jam_in' => '07:00:00',
            ]);
        }

        app(TrustEngineService::class)->evaluate(
            nisn: '9999000004',
            absenType: 'in',
            method: 'gps',
            faceDistance: null,
            lokasi: '-7.671325,110.589607',
            tglPresensi: '2026-11-04',
            jamAbsen: '07:30:00',
        );

        $signals = TrustScore::query()->firstOrFail()->raw_signals;
        $this->assertSame(30, $signals['time_difference_minutes']);
    }

    public function test_invalid_location_reduces_the_score_to_rejected(): void
    {
        $result = app(TrustEngineService::class)->evaluate(
            nisn: '99990003',
            absenType: 'in',
            method: 'gps',
            faceDistance: null,
            lokasi: '0,0',
            tglPresensi: '2026-11-04',
            jamAbsen: '07:00:00',
        );

        $this->assertSame(35, $result['score']);
        $this->assertSame('rejected', $result['verdict']);
        $this->assertSame(0, $result['components']['gps']);
    }
}
