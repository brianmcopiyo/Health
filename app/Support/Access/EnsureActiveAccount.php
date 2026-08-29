<?php

namespace App\Support\Access;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'canAuthenticate') && ! $user->canAuthenticate()) {
            if (method_exists($user, 'revokeAccessTokens')) {
                $user->revokeAccessTokens();
            }

            abort(401, AccountStatus::denialMessage($user->status ?? null));
        }

        return $next($request);
    }
}
