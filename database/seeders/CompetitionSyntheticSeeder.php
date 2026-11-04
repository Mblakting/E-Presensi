<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * [DIGIFEST 2026 - SYNTHETIC DATA ONLY]
 *
 * Seeder ini membuat data FIKTIF untuk keperluan demo lomba.
 * - Nama: karakter fiktif
 * - NISN: prefix 9999XXXXXX (jelas palsu, tidak mungkin bentrok dengan siswa nyata)
 * - Foto: tidak ada (null)
 * - Face descriptor: tidak ada (null) — didaftarkan manual saat demo
 *
 * JANGAN jalankan di database production klien.
 * Jalankan HANYA di instance Railway lomba yang terpisah.
 */
class CompetitionSyntheticSeeder extends Seeder
{
    // NISN prefix 9999 = jelas sintetis, tidak akan bentrok dengan NISN nyata
    private array $syntheticStudents = [
        ['nisn' => '9999000001', 'nama_lengkap' => 'Arjuna Wibisana',    'kelas' => 'XII TKJ 1'],
        ['nisn' => '9999000002', 'nama_lengkap' => 'Srikandi Dewi',      'kelas' => 'XII TKJ 1'],
        ['nisn' => '9999000003', 'nama_lengkap' => 'Bima Sakti',         'kelas' => 'XI RPL 2'],
        ['nisn' => '9999000004', 'nama_lengkap' => 'Nakula Pratama',     'kelas' => 'XI RPL 2'],
        ['nisn' => '9999000005', 'nama_lengkap' => 'Sadewa Nugraha',     'kelas' => 'X MM 1'],
        ['nisn' => '9999000006', 'nama_lengkap' => 'Kunti Rahayu',       'kelas' => 'X MM 1'],
        ['nisn' => '9999000007', 'nama_lengkap' => 'Drupadi Lestari',    'kelas' => 'XII TKJ 2'],
        ['nisn' => '9999000008', 'nama_lengkap' => 'Kresna Wijaya',      'kelas' => 'XII TKJ 2'],
        ['nisn' => '9999000009', 'nama_lengkap' => 'Gatotkaca Putra',    'kelas' => 'XI TKJ 1'],
        ['nisn' => '9999000010', 'nama_lengkap' => 'Abimanyu Santosa',   'kelas' => 'XI TKJ 1'],
    ];

    public function run(): void
    {
        $this->command->info('[DIGIFEST] Membuat data sintetis untuk demo lomba...');
        $this->command->warn('[DIGIFEST] NISN prefix 9999 = data fiktif, bukan siswa nyata.');

        $inserted = 0;
        $skipped  = 0;
        $demoPassword = null;

        foreach ($this->syntheticStudents as $student) {
            // Skip jika NISN sudah ada (idempotent)
            if (DB::table('siswa')->where('nisn', $student['nisn'])->exists()) {
                $skipped++;
                continue;
            }

            if ($demoPassword === null) {
                $configuredPassword = config('competition.demo_password');
                $demoPassword = $configuredPassword ?: Str::password(24);
                if (!$configuredPassword) {
                    $this->command->warn('[DIGIFEST] Password demo acak (simpan sekarang, tidak akan ditampilkan lagi): ' . $demoPassword);
                }
            }

            DB::table('siswa')->insert([
                'nisn'         => $student['nisn'],
                'nama_lengkap' => $student['nama_lengkap'],
                'kelas'        => $student['kelas'],
                'password'     => Hash::make($demoPassword),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $inserted++;
        }
        $this->command->info("[DIGIFEST] {$inserted} siswa sintetis dibuat, {$skipped} dilewati (sudah ada).");
        $this->command->info("[DIGIFEST] {$inserted} siswa sintetis dibuat, {$skipped} dilewati (sudah ada).");

        // Buat juga data presensi sintetis untuk demo anomaly detection (Phase 5)
        $this->seedSyntheticAttendance();
    }

    private function seedSyntheticAttendance(): void
    {
        $this->command->info('[DIGIFEST] Membuat data presensi sintetis (30 hari terakhir)...');

        $nisns = collect($this->syntheticStudents)->pluck('nisn');
        $inserted = 0;

        foreach ($nisns as $nisn) {
            // Cek siswa ada
            if (!DB::table('siswa')->where('nisn', $nisn)->exists()) continue;

            for ($daysAgo = 30; $daysAgo >= 1; $daysAgo--) {
                $date = now()->subDays($daysAgo)->toDateString();

                // Skip weekend
                $dayOfWeek = now()->subDays($daysAgo)->dayOfWeek;
                if ($dayOfWeek === 0 || $dayOfWeek === 6) continue;

                // Skip jika sudah ada
                if (DB::table('presensi')->where('nisn', $nisn)->where('tgl_presensi', $date)->exists()) continue;

                // Simulasi pola: 2 siswa sering terlambat (untuk demo anomaly detection)
                $isLatePattern = in_array($nisn, ['9999000003', '9999000007']);
                $isAbsentPattern = in_array($nisn, ['9999000005']);

                // 20% bolos untuk pola absen, 5% untuk normal
                $absentChance = $isAbsentPattern ? 0.25 : 0.05;
                if (rand(1, 100) / 100 < $absentChance) continue;

                // Jam masuk: normal 07:00-07:15, terlambat 07:30-08:30
                if ($isLatePattern && rand(0, 1)) {
                    $jamIn = sprintf('0%d:%02d:00', rand(7, 8), rand(30, 59));
                } else {
                    $jamIn = sprintf('07:%02d:00', rand(0, 15));
                }

                DB::table('presensi')->insert([
                    'nisn'         => $nisn,
                    'tgl_presensi' => $date,
                    'jam_in'       => $jamIn,
                    'jam_out'      => '15:' . sprintf('%02d', rand(0, 30)) . ':00',
                    'lokasi_in'    => '-7.671325,110.589607',
                    'lokasi_out'   => '-7.671325,110.589607',
                    'created_at'   => $date . ' ' . $jamIn,
                    'updated_at'   => $date . ' 15:00:00',
                ]);
                $inserted++;
            }
        }

        $this->command->info("[DIGIFEST] {$inserted} record presensi sintetis dibuat.");
    }
}
