<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('login', ['locale' => 'fa']));
        }
        if (! $user->role->isStaff()) {
            abort(403, __('ui.errors.mfa_invalid'));
        }

        return $next($request);
    }
}
