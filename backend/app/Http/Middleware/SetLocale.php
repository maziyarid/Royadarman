<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) ($request->route('locale') ?: $request->header('X-Locale', $request->user()?->locale ?? 'fa'));
        if (! in_array($locale, ['fa', 'ar', 'en'], true)) {
            abort(404);
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
