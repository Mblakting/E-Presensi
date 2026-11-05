<?php

namespace Tests\Feature;

use App\Competition\Http\CompetitionNavigationMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class CompetitionNavigationMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $admin = new User;
        $admin->setAttribute('id', 7);
        $this->actingAs($admin, 'user');
    }

    public function test_disabled_flag_preserves_html_response_byte_for_byte(): void
    {
        config(['competition.competition_nav' => false]);
        $html = '<!doctype html><html><body><main>Client page</main></body></html>';
        $response = $this->runMiddleware(Request::create('/dashboardadmin'), $html);

        $this->assertSame($html, $response->getContent());
    }

    public function test_enabled_flag_injects_one_link_into_tabler_navigation(): void
    {
        config(['competition.competition_nav' => true]);
        $html = '<html><body><ul class="navbar-nav pt-lg-3"><li>Dashboard</li></ul></body></html>';
        $response = $this->runMiddleware(Request::create('/dashboardadmin'), $html);

        $this->assertSame(1, substr_count($response->getContent(), 'data-competition-navigation'));
        $this->assertStringContainsString('href="'.url('/competition').'"', $response->getContent());
        $this->assertStringContainsString('Fitur Lomba', $response->getContent());

        $secondResponse = $this->runMiddleware(
            Request::create('/dashboardadmin'),
            (string) $response->getContent()
        );
        $this->assertSame(1, substr_count($secondResponse->getContent(), 'data-competition-navigation'));
    }

    public function test_non_html_json_ajax_redirect_and_download_responses_are_unchanged(): void
    {
        config(['competition.competition_nav' => true]);
        $html = '<html><body>Page</body></html>';
        $middleware = app(CompetitionNavigationMiddleware::class);

        $jsonRequest = Request::create('/dashboardadmin', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $json = response()->json(['ok' => true]);
        $this->assertSame($json, $middleware->handle($jsonRequest, static fn () => $json));

        $ajaxRequest = Request::create('/dashboardadmin', 'GET', server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $ajax = new Response($html, 200, ['Content-Type' => 'text/html']);
        $this->assertSame($html, $middleware->handle($ajaxRequest, static fn () => $ajax)->getContent());

        $redirect = new Response('', 302, ['Location' => '/panel', 'Content-Type' => 'text/html']);
        $this->assertSame('', $middleware->handle(Request::create('/dashboardadmin'), static fn () => $redirect)->getContent());

        $download = new Response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="page.html"',
        ]);
        $this->assertSame($html, $middleware->handle(Request::create('/dashboardadmin'), static fn () => $download)->getContent());
    }

    public function test_missing_navigation_uses_floating_fallback_and_missing_body_fails_safely(): void
    {
        config(['competition.competition_nav' => true]);
        $fallback = $this->runMiddleware(
            Request::create('/dashboardadmin'),
            '<html><body><main>Legacy mobile page</main></body></html>'
        );
        $this->assertSame(1, substr_count($fallback->getContent(), 'data-competition-navigation'));
        $this->assertStringContainsString('position:fixed', $fallback->getContent());

        $withoutBody = '<html><main>No body element</main></html>';
        $response = $this->runMiddleware(Request::create('/dashboardadmin'), $withoutBody);
        $this->assertSame($withoutBody, $response->getContent());
    }

    public function test_competition_pages_do_not_receive_client_navigation_injection(): void
    {
        config(['competition.competition_nav' => true]);
        $html = '<html><body><ul class="navbar-nav"><li>Menu kompetisi</li></ul></body></html>';

        $response = $this->runMiddleware(Request::create('/competition/reviews'), $html);

        $this->assertSame($html, $response->getContent());
    }

    private function runMiddleware(Request $request, string $html): Response
    {
        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);

        return app(CompetitionNavigationMiddleware::class)->handle($request, static fn () => $response);
    }
}
