<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
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

        $this->get('/errors/404')->assertOk()->assertSee('id="app"', false);
        $this->get('/errors/500')->assertOk()->assertSee('id="app"', false);
        $this->get('/missing-workspace-page')->assertOk()->assertSee('id="app"', false);
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
}
