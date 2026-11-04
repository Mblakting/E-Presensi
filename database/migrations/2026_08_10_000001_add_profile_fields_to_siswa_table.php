<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            if (! Schema::hasColumn('siswa', 'no_hp')) {
                $table->string('no_hp', 20)->nullable()->after('kelas');
            }

            if (! Schema::hasColumn('siswa', 'alamat')) {
                $table->text('alamat')->nullable()->after('no_hp');
            }

            if (! Schema::hasColumn('siswa', 'nama_orang_tua')) {
                $table->string('nama_orang_tua', 100)->nullable()->after('alamat');
            }

            if (! Schema::hasColumn('siswa', 'foto')) {
                $table->string('foto')->nullable()->after('nama_orang_tua');
            }
        });

        $foreignKeys = DB::getDriverName() === 'mysql'
            ? collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'siswa'
                AND REFERENCED_TABLE_NAME IS NOT NULL
                AND COLUMN_NAME = 'kode_jurusan'"
            ))->pluck('CONSTRAINT_NAME')
            : collect(Schema::getForeignKeys('siswa'))
                ->filter(fn (array $foreignKey): bool => in_array('kode_jurusan', $foreignKey['columns'], true));

        if ($foreignKeys->isNotEmpty()) {
            Schema::table('siswa', function (Blueprint $table) {
                $table->dropForeign(['kode_jurusan']);
                $table->string('kode_jurusan')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            if (Schema::hasColumn('siswa', 'alamat')) {
                $table->dropColumn('alamat');
            }

            if (Schema::hasColumn('siswa', 'nama_orang_tua')) {
                $table->dropColumn('nama_orang_tua');
            }
        });
    }
};
