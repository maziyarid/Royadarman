@php
    $pathLocale = request()->segment(1);
    $locale = in_array($pathLocale, ['fa', 'ar', 'en'], true) ? $pathLocale : app()->getLocale();
    app()->setLocale($locale);
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale, ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2947a3">
    <meta name="robots" content="noindex, follow">
    <title>{{ __('ui.error_404_title') }}</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/site.css?v=20260910">
</head>
<body>
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<header class="site-header">
    <div class="shell header-row">
        <a class="brand" href="/{{ $locale }}/" aria-label="{{ __('ui.meta_title') }}">
            <img src="/assets/brand-mark.svg" alt="" width="54" height="54">
            <span><strong>{{ $locale === 'en' ? 'Royadarman' : 'رویادرمان' }}</strong><small>{{ __('ui.brand_subtitle') }}</small></span>
        </a>
    </div>
</header>

<main id="main">
    <section class="error-section">
        <div class="shell">
            <p class="kicker">404</p>
            <h1>{{ __('ui.error_404_title') }}</h1>
            <p class="lede">{{ __('ui.error_404_text') }}</p>
            <div class="hero-actions">
                <a class="button primary" href="/{{ $locale }}/">{{ __('ui.error_404_home') }}</a>
                <a class="button ghost" href="/{{ $locale }}/blog/">{{ __('ui.blog_index_heading') }}</a>
            </div>
        </div>
    </section>
</main>

<footer><div class="shell footer-row"><div class="brand"><img src="/assets/brand-mark.svg" alt="" width="46" height="46"><span><strong>{{ $locale === 'en' ? 'Royadarman' : 'رویادرمان' }}</strong><small>{{ __('ui.footer') }}</small></span></div><p>© {{ now()->setTimezone('Asia/Tehran')->format('Y') }}</p></div></footer>
</body>
</html>
