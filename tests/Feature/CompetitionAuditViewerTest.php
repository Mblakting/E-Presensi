<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompetitionAuditViewerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['competition.audit_viewer' => true]);
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
            $table->string('nama_lengkap');
            $table->string('kelas');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        $migration = require base_path('database/migrations/2026_digifest_002_create_competition_audit_logs_table.php');
        $migration->up();
        require app_path('Competition/Routes/audit.php');
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    public function test_admin_can_view_audits_and_view_access_is_logged_read_only(): void
    {
        DB::table('competition_audit_logs')->insert([
            'event_type' => 'consent_withdrawn',
            'actor_type' => 'admin',
            'actor_id' => '8',
            'subject_nisn' => '9999000001',
            'ip_address' => '192.0.2.10',
            'user_agent' => 'synthetic test agent',
            'is_synthetic' => true,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.invalid',
            'password' => 'test-password',
        ]);
        $this->actingAs($admin, 'user');

        $this->get(route('competition.audit.index'))
            ->assertOk()
            ->assertSee('consent_withdrawn')
            ->assertSee('…0001')
            ->assertDontSee('192.0.2.10')
            ->assertDontSee('synthetic test agent');
        $this->assertDatabaseHas('competition_audit_logs', [
            'event_type' => 'audit_viewer_accessed',
            'actor_id' => (string) $admin->id,
        ]);

        $route = Route::getRoutes()->getByName('competition.audit.index');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }

    public function test_student_guard_cannot_access_audit_viewer(): void
    {
        $student = Siswa::query()->create([
            'nisn' => '9999000001',
            'nama_lengkap' => 'Siswa Uji',
            'kelas' => 'XII',
            'password' => 'test-password',
        ]);
        $this->actingAs($student, 'siswa');

        $this->get(route('competition.audit.index'))->assertRedirect(route('loginadmin'));
        $this->assertDatabaseCount('competition_audit_logs', 0);
    }
}
