<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __('auth_ui.title') }}</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/assets/pwa/icon-192.png">
<meta name="theme-color" content="#2947A3">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes"><link rel="stylesheet" href="/assets/workspace.css?v=20260913"><script src="/assets/auth-login.js?v=20260929" defer></script>
    @vite(['resources/js/passkeys.js'])
</head>
<body class="auth-body">
<main class="auth-shell" data-auth-login data-passkey-login-root data-locale="{{ $locale }}" data-otp-length="{{ $otpLength ?? 6 }}" data-error="{{ __('auth_ui.error') }}" data-sent="{{ __('auth_ui.sent') }}" data-mfa-hint="{{ __('auth_ui.mfa_hint') }}" data-invalid="{{ __('auth_ui.invalid_credentials') }}" data-panel="{{ route('panel',['locale'=>$locale]) }}" data-working="{{ __('auth_ui.passkey_working') }}">
<section class="auth-aside" aria-label="Royadarman">
<div><img src="/assets/brand-mark.svg" alt=""><h2>{{ __('auth_ui.title') }}</h2><p>{{ __('auth_ui.subtitle') }}</p></div>
<div class="auth-points"><div class="auth-point">{{ __('auth_ui.existing_only') }}</div><div class="auth-point">{{ __('auth_ui.totp') }}</div><div class="auth-point">{{ __('auth_ui.back') }}</div></div>
</section>
<section class="auth-main"><div class="auth-card">
<h1>{{ __('auth_ui.title') }}</h1><p>{{ __('auth_ui.subtitle') }}</p>
<div id="message" class="notice hidden" role="status"></div>
@if(empty($intakeEnabled))<div class="notice">{{ __('auth_ui.existing_only') }}</div>@endif
<form id="password-form"><div class="field"><label for="username">{{ __('auth_ui.username') }}</label><input id="username" name="username" dir="ltr" autocomplete="username" required maxlength="32"></div><div class="field"><label for="password">{{ __('auth_ui.password') }}</label><input id="password" name="password" type="password" dir="ltr" autocomplete="current-password" required maxlength="128"></div><details class="staff" data-password-mfa><summary>{{ __('auth_ui.additional_security') }}</summary><div class="field"><label for="password-totp">{{ __('auth_ui.totp') }}</label><input id="password-totp" name="totp_code" dir="ltr" inputmode="numeric" autocomplete="one-time-code"></div><div class="field"><label for="password-recovery">{{ __('auth_ui.recovery') }}</label><input id="password-recovery" name="recovery_code" dir="ltr" autocomplete="off"></div></details><button class="btn primary" type="submit">{{ __('auth_ui.sign_in') }}</button></form>
<button class="secondary" type="button" id="show-otp">{{ __('auth_ui.use_otp') }}</button>
<button class="secondary hidden" type="button" id="show-password">{{ __('auth_ui.use_password') }}</button>
<form id="challenge-form" class="hidden"><div class="field"><label for="mobile">{{ __('auth_ui.mobile') }}</label><input id="mobile" name="mobile" dir="ltr" inputmode="tel" autocomplete="tel" placeholder="0912…" required maxlength="32"></div><button class="btn primary" type="submit">{{ __('auth_ui.send') }}</button></form>
<form id="verify-form" class="hidden"><div class="field"><label for="code">{{ __('auth_ui.code') }}</label><input id="code" name="code" dir="ltr" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" required maxlength="{{ $otpLength ?? 6 }}" minlength="{{ $otpLength ?? 6 }}"></div><div class="staff" data-mfa-fields hidden><div class="field"><label for="totp_code">{{ __('auth_ui.totp') }}</label><input id="totp_code" name="totp_code" dir="ltr" inputmode="numeric" autocomplete="one-time-code"></div><div class="field"><label for="recovery_code">{{ __('auth_ui.recovery') }}</label><input id="recovery_code" name="recovery_code" dir="ltr" autocomplete="off"></div></div><button class="btn primary" type="submit">{{ __('auth_ui.verify') }}</button></form>
<div class="auth-divider"><span>{{ __('auth_ui.or') }}</span></div>
<p class="auth-passkey-copy">{{ __('auth_ui.passkey_intro') }}</p>
<button class="btn" type="button" data-passkey-login hidden>{{ __('auth_ui.passkey_button') }}</button>
<a class="secondary" href="{{ $locale === 'fa' ? route('public.home.fa') : route('public.home',['locale'=>$locale]) }}">{{ __('auth_ui.back') }}</a>
</div></section>
</main>
</body></html>
