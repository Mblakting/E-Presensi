<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BatchFaceController extends Controller
{
    public function index()
    {
        return view('face.batch');
    }

    /**
     * Ekstrak file ZIP ke folder sementara dan kembalikan daftar gambar + NISN.
     */
    public function extractZip(Request $request)
    {
        $request->validate([
            'file_zip' => 'required|file|mimes:zip|max:51200',
        ], [
            'file_zip.required' => 'File ZIP wajib diunggah.',
            'file_zip.mimes'    => 'File harus berformat .zip.',
            'file_zip.max'      => 'Ukuran file ZIP maksimal 50MB.',
        ]);

        // Bersihkan folder temp lama
        $tempDir = 'temp_faces';
        Storage::disk('public')->deleteDirectory($tempDir);
        Storage::disk('public')->makeDirectory($tempDir);

        $zipPath = $request->file('file_zip')->getRealPath();
        $zip     = new \ZipArchive();

        if ($zip->open($zipPath) !== true) {
            return response()->json(['status' => 'error', 'message' => 'Gagal membuka file ZIP.'], 422);
        }

        $allowedExt = ['jpg', 'jpeg', 'png'];
        $extracted  = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry    = $zip->getNameIndex($i);
            $basename = basename($entry);
            $ext      = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

            // Lewati folder dan file bukan gambar
            if (substr($entry, -1) === '/' || !in_array($ext, $allowedExt)) {
                continue;
            }

            $nisn = pathinfo($basename, PATHINFO_FILENAME);

            // Validasi NISN ada di database
            $siswa = DB::table('siswa')->where('nisn', $nisn)->first();
            if (!$siswa) {
                continue;
            }

            $destName = $nisn . '.' . $ext;
            $destPath = $tempDir . '/' . $destName;

            $content = $zip->getFromIndex($i);
            if ($content === false) continue;

            Storage::disk('public')->put($destPath, $content);

            $extracted[] = [
                'nisn'      => $nisn,
                'nama'      => $siswa->nama_lengkap,
                'url'       => asset('storage/' . $destPath),
                'temp_path' => $destPath,
            ];
        }

        $zip->close();

        if (empty($extracted)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada foto yang cocok dengan NISN siswa di database. Pastikan nama file = NISN siswa (contoh: 0012345678.jpg).',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'total'  => count($extracted),
            'data'   => $extracted,
        ]);
    }

    /**
     * Hapus folder temp setelah proses batch selesai.
     */
    public function cleanup()
    {
        Storage::disk('public')->deleteDirectory('temp_faces');
        return response()->json(['status' => 'success']);
    }
}
