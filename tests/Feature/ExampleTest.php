<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_frontend_error_paths_stay_on_the_spa(): void
    {
        $this->withoutVite();

        foreach (['/errors/404', '/errors/500', '/missing-workspace-page', '/patients/123'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('id="app"', false)
                ->assertSee('window.__PAGE_ERROR__ = null', false);
        }
    }

    public function test_asset_base_paths_are_not_treated_as_pages(): void
    {
        $this->withoutVite();

        $this->get('/build/patients/a2a42497-37d7-444b-a960-0f09e494c826')
            ->assertRedirect('/patients/a2a42497-37d7-444b-a960-0f09e494c826');
        $this->get('/build/assets/missing.js')->assertNotFound();
    }

    public function test_web_http_errors_replace_the_spa_on_the_failed_url(): void
    {
        $this->withoutVite();

        foreach ([403, 404, 429, 500] as $status) {
            $this->renderWebError(new HttpException($status, 'secret-leak'), '/patients/123')
                ->assertStatus($status)
                ->assertSee('id="app"', false)
                ->assertSee("window.__PAGE_ERROR__ = {$status}", false)
                ->assertDontSee('secret-leak');
        }

        $this->renderWebError(new TokenMismatchException('secret-leak'), '/visits/456')
            ->assertStatus(419)
            ->assertSee('id="app"', false)
            ->assertSee('window.__PAGE_ERROR__ = 419', false)
            ->assertDontSee('secret-leak');
    }

    public function test_unexpected_web_errors_render_the_spa_without_leaking(): void
    {
        $this->withoutVite();

        $this->renderWebError(new \RuntimeException('secret-leak'), '/patients/123')
            ->assertStatus(500)
            ->assertSee('id="app"', false)
            ->assertSee('window.__PAGE_ERROR__ = 500', false)
            ->assertDontSee('secret-leak');
    }

    public function test_unknown_api_routes_return_json(): void
    {
        $this->getJson('/api/missing-workspace-route')
            ->assertNotFound()
            ->assertJson(['message' => 'Not found.']);
    }

    public function test_unexpected_api_errors_do_not_expose_exceptions(): void
    {
        Route::get('/api/__boom', function () {
            throw new \RuntimeException('secret-leak');
        });

        $this->getJson('/api/__boom')
            ->assertStatus(500)
            ->assertJson(['message' => 'A server error occurred.'])
            ->assertDontSee('secret-leak');
    }

    private function renderWebError(\Throwable $exception, string $path): TestResponse
    {
        $request = Request::create($path, 'GET');
        $response = $this->app->make(ExceptionHandler::class)->render($request, $exception);

        return TestResponse::fromBaseResponse($response);
    }
}
