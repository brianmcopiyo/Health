<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $action, string $subject): Response
    {
        $user = $request->user();

        abort_unless($user && $user->hasPermission($action, $subject), 403, 'This action is unauthorized.');

        return $next($request);
    }
}
