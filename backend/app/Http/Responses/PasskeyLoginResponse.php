<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function toResponse($request): Response
    {
        $selectedLocale = $request->hasSession() ? $request->session()->get('ui_locale') : null;
        $locale = in_array($selectedLocale, ['fa', 'ar', 'en'], true) ? $selectedLocale : Auth::user()?->locale;
        $locale = in_array($locale, ['fa', 'ar', 'en'], true) ? $locale : 'fa';
        $target = route('panel', ['locale' => $locale]);

        if ($request->wantsJson()) {
            return new JsonResponse(['redirect' => $target]);
        }

        return redirect()->intended($target);
    }
}
