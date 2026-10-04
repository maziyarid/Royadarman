<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeLocale = $request->route('locale');
        $savedLocale = $request->hasSession() ? $request->session()->get('ui_locale') : null;
        $savedLocale = in_array($savedLocale, ['fa', 'ar', 'en'], true) ? $savedLocale : null;
        $locale = $routeLocale ?: $request->header('X-Locale', $savedLocale ?? $request->user()?->locale ?? 'fa');
        if (! in_array($locale, ['fa', 'ar', 'en'], true)) {
            abort(404);
        }
        app()->setLocale($locale);

        // A page selection is a browser preference; an API/document language is not.
        if ($routeLocale && ! $request->is('api/*') && $request->hasSession()) {
            $request->session()->put('ui_locale', $locale);
        }

        return $next($request);
    }
}
