<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompetitionSyntheticSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['competition.demo_password' => null]);
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
            $table->string('nama_lengkap');
            $table->string('kelas');
            $table->string('password');
            $table->timestamps();
        });
        Schema::create('presensi', function (Blueprint $table): void {
            $table->id();
            $table->string('nisn');
            $table->date('tgl_presensi');
            $table->time('jam_in')->nullable();
            $table->time('jam_out')->nullable();
            $table->string('lokasi_in')->nullable();
            $table->string('lokasi_out')->nullable();
            $table->timestamps();
        });
        $migration = require base_path('database/migrations/2026_digifest_005_create_competition_consents_table.php');
        $migration->up();
    }

    public function test_empty_password_configuration_generates_and_prints_one_random_password(): void
    {
        $this->assertSame(0, Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\CompetitionSyntheticSeeder',
        ]));

        $output = Artisan::output();
        $this->assertSame(1, substr_count($output, 'Password demo acak'));
        $this->assertSame(10, DB::table('siswa')->count());
        $this->assertSame(10, DB::table('competition_consents')->where('status', 'granted')->count());
        $this->assertSame(0, DB::table('competition_consents')->where('siswa_nisn', 'NOT LIKE', '9999%')->count());
        $this->assertSame(0, DB::table('siswa')->whereRaw("nisn NOT LIKE '9999%'")->count());
        $this->assertSame(1, preg_match('/Password demo acak[^:]*: (\\S+)/', $output, $matches));

        foreach (DB::table('siswa')->get() as $student) {
            $this->assertTrue(Hash::check($matches[1], $student->password));
        }
    }

    public function test_configured_password_is_used_without_printing_it(): void
    {
        config(['competition.demo_password' => 'test-only-demo-password']);

        $this->assertSame(0, Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\CompetitionSyntheticSeeder',
        ]));

        $output = Artisan::output();
        $this->assertStringNotContainsString('Password demo acak', $output);
        $this->assertTrue(Hash::check('test-only-demo-password', DB::table('siswa')->value('password')));
    }
}
