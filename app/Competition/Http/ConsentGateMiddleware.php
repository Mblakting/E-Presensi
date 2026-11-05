<?php

namespace App\Competition\Http;

use App\Competition\Services\ConsentService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ConsentGateMiddleware
{
    public function __construct(private readonly ConsentService $consents) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('competition.consents.*')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (in_array($routeName, ['presensi.face', 'presensi.face.store', 'face.descriptors'], true)) {
            $nisn = (string) Auth::guard('siswa')->id();
            $textApproved = (bool) config('competition.consent.text_approved', false);
            if ($nisn === '' || ! $textApproved || ! $this->consents->isGranted($nisn)) {
                $studentConsentAvailable = Route::has('competition.student-consents.index');
                $message = ! $textApproved
                    ? 'Presensi wajah diblokir karena teks persetujuan belum disetujui. Gunakan presensi non-wajah atau kembali setelah pengelola meninjau teks.'
                    : 'Persetujuan pemrosesan wajah belum aktif. Baca informasi persetujuan atau gunakan presensi non-wajah.';

                return $this->denied(
                    $request,
                    $message,
                    $studentConsentAvailable ? route('competition.student-consents.index') : null
                );
            }

            if ($routeName === 'face.descriptors') {
                return $this->descriptorsResponse();
            }

            $response = $next($request);

            return $response;
        }

        if ($routeName === 'face.enroll') {
            $nisn = (string) $request->route('nisn');
            if (! $this->consents->isGranted($nisn)) {
                return redirect()->route('competition.consents.index')
                    ->with('warning', 'Pendaftaran wajah diblokir: persetujuan belum aktif.');
            }
        }

        if ($routeName === 'face.save') {
            $nisn = (string) $request->input('nisn');
            if ($nisn === '' || ! $this->consents->isGranted($nisn)) {
                return $this->denied(
                    $request,
                    'Descriptor tidak disimpan karena persetujuan pemrosesan wajah belum aktif.',
                    route('competition.consents.index')
                );
            }
        }

        if ($routeName === 'batch.face.extract') {
            $deniedNisns = $this->bulkUploadNisnsWithoutConsent($request);
            if ($deniedNisns !== []) {
                return $this->denied(
                    $request,
                    'Ekstraksi ZIP dibatalkan sebelum file diekstrak: persetujuan wajah belum aktif untuk '.count($deniedNisns).' siswa.',
                    route('competition.consents.index')
                );
            }
        }

        return $next($request);
    }

    private function descriptorsResponse(): JsonResponse
    {
        $grantedNisns = $this->consents->allGrantedNisns();
        $siswas = DB::table('siswa')
            ->select('nisn', 'nama_lengkap', 'kelas', 'foto', 'face_descriptor')
            ->whereNotNull('face_descriptor')
            ->whereIn('nisn', $grantedNisns)
            ->get();

        $data = $siswas->map(static function (object $siswa): array {
            return [
                'nisn' => $siswa->nisn,
                'nama_lengkap' => $siswa->nama_lengkap,
                'kelas' => $siswa->kelas,
                'foto' => ! empty($siswa->foto)
                    ? asset('storage/uploads/foto_siswa/'.$siswa->foto)
                    : null,
                'face_descriptor' => json_decode($siswa->face_descriptor, true),
            ];
        });

        return new JsonResponse(['status' => 'success', 'data' => $data], 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @return list<string>
     */
    private function bulkUploadNisnsWithoutConsent(Request $request): array
    {
        $file = $request->file('file_zip');
        if (! $file || ! class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            return [];
        }

        $candidateNisns = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if (! is_string($entry) || str_ends_with($entry, '/')) {
                continue;
            }

            $basename = basename($entry);
            if (! in_array(strtolower(pathinfo($basename, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }

            $candidateNisns[] = pathinfo($basename, PATHINFO_FILENAME);
        }
        $zip->close();

        $knownNisns = DB::table('siswa')
            ->whereIn('nisn', array_unique($candidateNisns))
            ->pluck('nisn')
            ->map(static fn (mixed $nisn): string => (string) $nisn)
            ->all();

        return array_values(array_diff($knownNisns, $this->consents->grantedNisns($knownNisns)));
    }

    private function denied(Request $request, string $message, ?string $alternative = null): Response
    {
        if ($request->expectsJson() || $request->routeIs('face.save', 'batch.face.extract')) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $message,
                'alternative' => $alternative ?? route('presensi.create'),
            ], 403);
        }

        return redirect()->to($alternative ?? route('presensi.create'))->with('warning', $message);
    }
}
