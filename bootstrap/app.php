<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\RotateEncryption::class,
        \App\Console\Commands\EncryptedBackup::class,
        \App\Console\Commands\PurgeExpiredRecords::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');
        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);
        $middleware->appendToGroup('api', \App\Support\Access\EnsureActiveAccount::class);
        $middleware->append([
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ForceHttps::class,
        ]);
        $middleware->throttleApi();
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (
                ($request->is('api/*') || $request->expectsJson())
                && ! $exception instanceof ValidationException
                && ! $exception instanceof AuthenticationException
                && ! $exception instanceof HttpExceptionInterface
            ) {
                return response()->json(['message' => 'A server error occurred.'], 500);
            }

            if ($request->is('api/*') || $request->expectsJson() || $request->is('up')) {
                return;
            }

            if ($exception instanceof ValidationException || $exception instanceof AuthenticationException) {
                return;
            }

            if ($exception instanceof \Illuminate\Session\TokenMismatchException) {
                return response()->view('application', ['pageError' => 419], 419);
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            if ($status < 400) {
                return;
            }

            return response()->view('application', ['pageError' => $status], $status);
        });
    })->create();
