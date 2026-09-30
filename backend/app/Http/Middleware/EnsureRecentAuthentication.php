<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRecentAuthentication
{
    private const MAX_AGE_MINUTES = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user
            && $user->is_active
            && ! $request->session()->get('panel_demo', false),
            403,
        );

        $authenticatedAt = $user->last_authenticated_at;

        abort_unless(
            $authenticatedAt !== null
            && $authenticatedAt->greaterThanOrEqualTo(now()->subMinutes(self::MAX_AGE_MINUTES)),
            423,
            __('panel.security.reauthenticate'),
        );

        return $next($request);
    }
}
