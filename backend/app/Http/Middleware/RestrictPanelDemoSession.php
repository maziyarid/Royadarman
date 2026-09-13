<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RestrictPanelDemoSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! (bool) $request->session()->get('panel_demo', false)) {
            return $next($request);
        }

        // A fresh signed demo link may replace an old/stale demo session. The
        // route's own signed middleware still validates the bearer link.
        if ($request->routeIs('demo.panel.access')) {
            return $next($request);
        }

        $user = $request->user();
        $expectedUserId = (string) $request->session()->get('panel_demo_user_id', '');

        if (! (bool) config('royadarman.panel_demo_access') || ! $user || (string) $user->id !== $expectedUserId) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Demo session is no longer valid.');
        }

        if ($request->is('api/v1/auth/logout') && $request->isMethod('POST')) {
            return $next($request);
        }

        if (($request->isMethod('GET') || $request->isMethod('HEAD')) && $request->routeIs('panel')) {
            return $next($request);
        }

        abort(403, 'Demo sessions are restricted to the read-only role panel.');
    }
}
