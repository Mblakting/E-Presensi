<?php

namespace App\Competition\Http;

use App\Competition\Services\ConsentService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            if ($nisn === '' || ! $this->consents->isGranted($nisn)) {
                return $this->denied($request, 'Persetujuan pemrosesan wajah belum aktif. Gunakan presensi non-wajah atau hubungi pengelola.');
            }

            $response = $next($request);

            if ($routeName === 'face.descriptors'
                && str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
                $payload = json_decode((string) $response->getContent(), true);
                if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
                    $nisns = array_values(array_filter(array_map(
                        static fn (mixed $item): ?string => is_array($item) && isset($item['nisn']) ? (string) $item['nisn'] : null,
                        $payload['data']
                    )));
                    $granted = array_fill_keys($this->consents->grantedNisns($nisns), true);
                    $payload['data'] = array_values(array_filter(
                        $payload['data'],
                        static fn (mixed $item): bool => is_array($item)
                            && isset($item['nisn'])
                            && isset($granted[(string) $item['nisn']])
                    ));

                    return response()->json($payload, $response->getStatusCode(), $response->headers->all());
                }
            }

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

        return redirect()->route('presensi.create')->with('warning', $message);
    }
}
