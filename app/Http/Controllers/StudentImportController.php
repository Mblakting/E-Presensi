<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StudentImportController extends Controller
{
    public function index()
    {
        return view('siswa.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file_csv' => 'required|file|mimes:csv,txt|max:2048',
        ], [
            'file_csv.required' => 'File CSV wajib diunggah.',
            'file_csv.mimes'    => 'File harus berformat .csv.',
            'file_csv.max'      => 'Ukuran file maksimal 2MB.',
        ]);

        $file   = $request->file('file_csv');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return redirect()->back()->with('warning', 'Gagal membaca file CSV.');
        }

        // Lewati baris header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return redirect()->back()->with('warning', 'File CSV kosong atau format tidak valid.');
        }

        // Normalisasi header (lowercase, trim)
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Validasi kolom wajib
        $required = ['nama', 'nisn', 'kelas'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                fclose($handle);
                return redirect()->back()->with('warning', "Kolom '{$col}' tidak ditemukan di header CSV. Header yang dibutuhkan: nama, nisn, kelas.");
            }
        }

        $idxNama  = array_search('nama', $header);
        $idxNisn  = array_search('nisn', $header);
        $idxKelas = array_search('kelas', $header);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $row      = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;

            $nama  = trim($data[$idxNama]  ?? '');
            $nisn  = trim($data[$idxNisn]  ?? '');
            $kelas = trim($data[$idxKelas] ?? '');

            if (empty($nama) || empty($nisn) || empty($kelas)) {
                $errors[] = "Baris {$row}: data tidak lengkap, dilewati.";
                $skipped++;
                continue;
            }

            // Abaikan duplikat berdasarkan NISN
            if (DB::table('siswa')->where('nisn', $nisn)->exists()) {
                $skipped++;
                continue;
            }

            try {
                DB::table('siswa')->insert([
                    'nisn'         => $nisn,
                    'nama_lengkap' => $nama,
                    'kelas'        => $kelas,
                    'password'     => Hash::make('12345'),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $imported++;
            } catch (\Exception $e) {
                Log::error("CSV Import error baris {$row}: " . $e->getMessage());
                $errors[] = "Baris {$row}: gagal disimpan.";
                $skipped++;
            }
        }

        fclose($handle);

        $message = "{$imported} siswa berhasil diimpor.";
        if ($skipped > 0) {
            $message .= " {$skipped} baris dilewati (duplikat atau tidak lengkap).";
        }

        return redirect()->route('siswa.index')->with('success', $message);
    }
}
