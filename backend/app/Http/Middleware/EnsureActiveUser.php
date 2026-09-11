<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && ! $user->is_active) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return (new JsonResponse([
                    'error' => ['code' => 'account.inactive'],
                    'request_id' => $request->attributes->get('request_id'),
                ], Response::HTTP_FORBIDDEN))->header('Cache-Control', 'private, no-store');
            }

            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
