<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateDemoAdminCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'competition.judge_email' => 'judge@example.test',
            'competition.judge_password' => 'safe-demo-password-2026',
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
            $table->string('nama_lengkap');
            $table->string('password');
        });
    }

    public function test_command_refuses_to_create_judge_when_real_student_nisn_exists(): void
    {
        DB::table('siswa')->insert([
            'nisn' => '1234567890',
            'nama_lengkap' => 'Synthetic fixture only',
            'password' => 'not-a-real-password',
        ]);

        $exitCode = Artisan::call('competition:create-demo-admin');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Refusing to create a judge account', Artisan::output());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_accepts_only_synthetic_student_records_and_is_idempotent(): void
    {
        DB::table('siswa')->insert([
            'nisn' => '9999000001',
            'nama_lengkap' => 'Synthetic fixture',
            'password' => 'not-a-real-password',
        ]);

        Artisan::call('competition:create-demo-admin');
        $firstOutput = Artisan::output();
        Artisan::call('competition:create-demo-admin');

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(Hash::check('safe-demo-password-2026', User::query()->firstOrFail()->password));
        $this->assertStringNotContainsString('safe-demo-password-2026', $firstOutput.Artisan::output());
    }

    public function test_command_rejects_password_shorter_than_twelve_characters(): void
    {
        config(['competition.judge_password' => 'short']);

        Artisan::call('competition:create-demo-admin');

        $this->assertStringContainsString('at least 12 characters', Artisan::output());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_allow_real_data_requires_explicit_acknowledgement_and_warns(): void
    {
        DB::table('siswa')->insert([
            'nisn' => '1234567890',
            'nama_lengkap' => 'Synthetic fixture only',
            'password' => 'not-a-real-password',
        ]);

        Artisan::call('competition:create-demo-admin', ['--allow-real-data' => true]);

        $this->assertStringContainsString('judge can view that data', Artisan::output());
        $this->assertDatabaseCount('users', 1);
    }
}
