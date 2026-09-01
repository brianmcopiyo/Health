<?php

namespace Tests\Feature;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HttpErrorPipelineTest extends TestCase
{
    public function test_disabled_probe_does_not_throw(): void
    {
        $this->withoutVite();
        config(['app.http_error_probe' => false]);

        $this->get('/__http-error-probe')
            ->assertStatus(404)
            ->assertSee('id="app"', false)
            ->assertSee('window.__PAGE_ERROR__ = 404', false)
            ->assertDontSee('http-error-probe');
    }

    public function test_web_probe_is_a_logged_http_500_on_the_original_url(): void
    {
        $this->withoutVite();
        config(['app.debug' => true, 'app.http_error_probe' => true]);
        $messages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$messages) {
            $messages[] = $event->message;
        });

        $this->get('/__http-error-probe')
            ->assertStatus(500)
            ->assertSee('id="app"', false)
            ->assertSee('window.__PAGE_ERROR__ = 500', false)
            ->assertDontSee('hms-shell')
            ->assertDontSee('http-error-probe');

        $this->assertTrue(collect($messages)->contains(fn ($message) => str_contains((string) $message, 'http-error-probe')));
    }

    public function test_api_probe_is_a_logged_json_500_without_leaking(): void
    {
        config(['app.debug' => true, 'app.http_error_probe' => true]);
        $messages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$messages) {
            $messages[] = $event->message;
        });

        $this->getJson('/api/__http-error-probe')
            ->assertStatus(500)
            ->assertJson(['message' => 'A server error occurred.'])
            ->assertDontSee('http-error-probe');

        $this->assertTrue(collect($messages)->contains(fn ($message) => str_contains((string) $message, 'http-error-probe')));
    }
}
