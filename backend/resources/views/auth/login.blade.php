<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __('auth_ui.title') }}</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/workspace.css?v=20260913"><script src="/assets/auth-login.js?v=20260913" defer></script>
</head>
<body class="auth-body">
<main class="auth-shell" data-auth-login data-locale="{{ $locale }}" data-error="{{ __('auth_ui.error') }}" data-sent="{{ __('auth_ui.sent') }}" data-panel="{{ route('panel',['locale'=>$locale]) }}">
<section class="auth-aside" aria-label="Royadarman">
<div><img src="/assets/brand-mark.svg" alt=""><h2>{{ __('auth_ui.title') }}</h2><p>{{ __('auth_ui.subtitle') }}</p></div>
<div class="auth-points"><div class="auth-point">{{ __('auth_ui.existing_only') }}</div><div class="auth-point">{{ __('auth_ui.totp') }}</div><div class="auth-point">{{ __('auth_ui.back') }}</div></div>
</section>
<section class="auth-main"><div class="auth-card">
<h1>{{ __('auth_ui.title') }}</h1><p>{{ __('auth_ui.subtitle') }}</p>
<div id="message" class="notice hidden" role="status"></div>
@if(empty($intakeEnabled))<div class="notice">{{ __('auth_ui.existing_only') }}</div>@endif
<form id="challenge-form"><div class="field"><label for="mobile">{{ __('auth_ui.mobile') }}</label><input id="mobile" name="mobile" dir="ltr" inputmode="tel" autocomplete="tel" placeholder="0912…" required maxlength="32"></div><button class="btn primary" type="submit">{{ __('auth_ui.send') }}</button></form>
<form id="verify-form" class="hidden"><div class="field"><label for="code">{{ __('auth_ui.code') }}</label><input id="code" name="code" dir="ltr" inputmode="numeric" autocomplete="one-time-code" required maxlength="6" minlength="6"></div><div class="staff"><div class="field"><label for="totp_code">{{ __('auth_ui.totp') }}</label><input id="totp_code" name="totp_code" dir="ltr" inputmode="numeric" autocomplete="one-time-code"></div><div class="field"><label for="recovery_code">{{ __('auth_ui.recovery') }}</label><input id="recovery_code" name="recovery_code" dir="ltr" autocomplete="off"></div></div><button class="btn primary" type="submit">{{ __('auth_ui.verify') }}</button></form>
<a class="secondary" href="{{ $locale === 'fa' ? route('public.home.fa') : route('public.home',['locale'=>$locale]) }}">{{ __('auth_ui.back') }}</a>
</div></section>
</main>
</body></html>
