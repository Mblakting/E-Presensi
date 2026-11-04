<?php

namespace App\Competition\Http;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Auth\Access\AuthorizationException;

class CompetitionErrorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (AuthorizationException $exception) {
            return $this->render($request, 403);
        } catch (ModelNotFoundException $exception) {
            return $this->render($request, 404);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [403, 404, 503], true)) {
                throw $exception;
            }

            return $this->render($request, $exception->getStatusCode());
        }
    }

    private function render(Request $request, int $status): Response
    {
        if (! $request->is('competition') && ! $request->is('competition/*')) {
            abort($status);
        }

        [$title, $message] = match ($status) {
            403 => ['Akses tidak tersedia', 'Silakan masuk dengan akun yang sesuai. Halaman admin hanya dapat dibuka oleh admin.'],
            503 => ['Fitur sementara tidak tersedia', 'Pemeriksaan kompetisi sedang mengalami kendala. Coba lagi nanti; untuk demo, admin dapat menonaktifkan FEATURE_TRUST_ENGINE_ENFORCE.'],
            default => ['Halaman tidak ditemukan', 'Rute mungkin belum aktif karena flag fiturnya nonaktif, atau URL tidak tersedia. Kembali ke pusat fitur lomba atau panduan juri.'],
        };

        return response()->view('competition::errors.status', [
            'statusCode' => $status,
            'title' => $title,
            'message' => $message,
        ], $status);
    }
}
