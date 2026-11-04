<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileFieldsMigrationTest extends TestCase
{
    public function test_profile_fields_migration_runs_up_and_down_with_sqlite_foreign_key(): void
    {
        $this->assertSame('sqlite', DB::getDriverName());

        Schema::create('jurusan', function (Blueprint $table): void {
            $table->string('kode_jurusan')->primary();
        });
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
            $table->string('kelas');
            $table->string('no_hp')->nullable();
            $table->string('foto')->nullable();
            $table->string('kode_jurusan');
            $table->foreign('kode_jurusan')
                ->references('kode_jurusan')
                ->on('jurusan');
        });

        $migration = require base_path('database/migrations/2026_08_10_000001_add_profile_fields_to_siswa_table.php');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('siswa', 'alamat'));
        $this->assertTrue(Schema::hasColumn('siswa', 'nama_orang_tua'));
        $this->assertSame([], Schema::getForeignKeys('siswa'));
        $kodeJurusan = collect(Schema::getColumns('siswa'))
            ->firstWhere('name', 'kode_jurusan');
        $this->assertTrue($kodeJurusan['nullable']);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('siswa', 'alamat'));
        $this->assertFalse(Schema::hasColumn('siswa', 'nama_orang_tua'));
        $this->assertTrue(Schema::hasTable('jurusan'));
        $this->assertTrue(Schema::hasTable('siswa'));
    }

    public function test_profile_fields_migration_skips_foreign_key_alter_when_no_foreign_key_exists(): void
    {
        Schema::create('siswa', function (Blueprint $table): void {
            $table->string('nisn')->primary();
            $table->string('kelas');
            $table->string('no_hp')->nullable();
            $table->string('foto')->nullable();
            $table->string('kode_jurusan')->nullable();
        });

        $migration = require base_path('database/migrations/2026_08_10_000001_add_profile_fields_to_siswa_table.php');

        $migration->up();

        $this->assertSame([], Schema::getForeignKeys('siswa'));
        $this->assertTrue(Schema::hasColumn('siswa', 'alamat'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('siswa', 'alamat'));
        $this->assertFalse(Schema::hasColumn('siswa', 'nama_orang_tua'));
    }
}
