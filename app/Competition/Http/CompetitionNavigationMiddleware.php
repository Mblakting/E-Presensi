<?php

namespace App\Competition\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CompetitionNavigationMiddleware
{
    private const MARKER = 'data-competition-navigation';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! config('competition.competition_nav', false)
            || (! Auth::guard('user')->check() && ! Auth::guard('siswa')->check())
            || $request->expectsJson()
            || $request->ajax()
            || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300
            || $response->headers->has('Content-Disposition')
            || ! str_contains(strtolower((string) $response->headers->get('Content-Type', '')), 'text/html')) {
            return $response;
        }

        try {
            $html = $response->getContent();
            if (! is_string($html) || str_contains($html, self::MARKER)) {
                return $response;
            }

            $menuPattern = '/<ul\b(?=[^>]*\bclass\s*=\s*(["\'])[^"\']*\bnavbar-nav\b[^"\']*\1)[^>]*>/i';
            $updated = preg_replace_callback(
                $menuPattern,
                fn (array $match): string => $match[0].$this->linkMarkup(),
                $html,
                1,
                $menuMatches
            );

            if ($menuMatches !== 1 || ! is_string($updated)) {
                $updated = preg_replace_callback(
                    '/<body\b[^>]*>/i',
                    fn (array $match): string => $match[0].$this->linkMarkup(true),
                    $html,
                    1,
                    $bodyMatches
                );
                if ($bodyMatches !== 1 || ! is_string($updated)) {
                    Log::warning('[Competition] Navigation link was not injected: HTML body was not found.');

                    return $response;
                }

            }

            $response->setContent($updated);
        } catch (Throwable $exception) {
            Log::warning('[Competition] Navigation link injection failed safely.', [
                'exception_class' => $exception::class,
            ]);
        }

        return $response;
    }

    private function linkMarkup(bool $floating = false): string
    {
        $href = htmlspecialchars(url('/competition'), ENT_QUOTES, 'UTF-8');

        if ($floating) {
            return '<a '.self::MARKER.' href="'.$href.'" aria-label="Buka pusat fitur lomba" style="position:fixed;right:1rem;bottom:1rem;z-index:1080;padding:.65rem 1rem;border-radius:.5rem;background:#206bc4;color:#fff;text-decoration:none;box-shadow:0 .25rem .75rem rgba(0,0,0,.2)">Fitur Lomba</a>';
        }

        return '<li class="nav-item" '.self::MARKER.'><a class="nav-link" href="'.$href.'"><span class="nav-link-title">Fitur Lomba</span></a></li>';
    }
}
