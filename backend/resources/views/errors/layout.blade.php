@php
    $segment = request()->segment(1);
    $locale = in_array($segment, ['ar', 'en'], true) ? $segment : 'fa';
    app()->setLocale($locale);
    $code = $code ?? '404';
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,follow">
    <title>{{ __('ui.errors.'.$code.'_title') }}</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/font.css?v=20260915">
    <link rel="stylesheet" href="/assets/site.css?v=20260915">
</head>
<body>
@include('public.partials.header')
<main id="main">
    <section class="error-section">
        <div class="shell">
            <p class="error-code">{{ $code }}</p>
            <h1>{{ __('ui.errors.'.$code.'_title') }}</h1>
            <p class="hero-lead">{{ __('ui.errors.'.$code.'_text') }}</p>
            <p class="muted">{{ __('ui.errors.saved') }}</p>
            <div class="hero-actions">
                <a class="button primary" href="{{ $locale === 'fa' ? route('public.home.fa') : route('public.home', ['locale' => $locale]) }}">{{ __('ui.errors.home') }}</a>
                <a class="button ghost" href="{{ $locale === 'fa' ? route('public.contact.fa') : route('public.contact', ['locale' => $locale]) }}">{{ __('site.nav.contact') }}</a>
            </div>
        </div>
    </section>
</main>
@include('public.partials.footer')
</body>
</html>
