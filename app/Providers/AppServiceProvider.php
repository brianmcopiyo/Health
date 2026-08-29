<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(180)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
