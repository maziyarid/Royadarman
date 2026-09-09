<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2947a3">
    <meta name="description" content="{{ __('ui.meta_description') }}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <title>{{ __('ui.meta_title') }}</title>
    <link rel="canonical" href="{{ url('/'.app()->getLocale().'/') }}">
    @foreach(['fa','ar','en'] as $language)<link rel="alternate" hreflang="{{ $language }}" href="{{ url('/'.$language.'/') }}">@endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('/fa/') }}">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/site.css?v=20260908">
    <script src="/assets/site.js?v=20260908" defer></script>
</head>
<body>
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<header class="site-header">
    <div class="shell header-row">
        <a class="brand" href="/{{ app()->getLocale() }}/" aria-label="{{ __('ui.meta_title') }}">
            <img src="/assets/brand-mark.svg" alt="" width="54" height="54">
            <span><strong>{{ app()->getLocale() === 'en' ? 'Royadarman' : 'رویا درمان' }}</strong><small>{{ __('ui.brand_subtitle') }}</small></span>
        </a>
        <nav aria-label="{{ __('ui.nav.services') }}"><a href="#services">{{ __('ui.nav.services') }}</a><a href="#path">{{ __('ui.nav.path') }}</a><a href="#trust">{{ __('ui.nav.trust') }}</a><a href="#faq">{{ __('ui.nav.questions') }}</a></nav>
        <div class="header-actions">
            <details class="language"><summary>{{ strtoupper(app()->getLocale()) }}<span aria-hidden="true">⌄</span></summary><div>@foreach(['fa' => 'فارسی','ar' => 'العربية','en' => 'English'] as $code => $label)<a lang="{{ $code }}" dir="{{ $code === 'en' ? 'ltr' : 'rtl' }}" hreflang="{{ $code }}" href="/{{ $code }}/" @if($code === app()->getLocale()) aria-current="page" @endif>{{ $label }}</a>@endforeach</div></details>
            <a class="button primary desktop-cta" href="#start">{{ __('ui.cta') }}</a>
        </div>
    </div>
</header>

<main id="main">
    <section class="hero">
        <div class="shell hero-grid">
            <div class="hero-copy">
                <p class="signal"><i aria-hidden="true"></i>{{ __('ui.status') }}</p>
                <h1>{{ __('ui.hero_title') }}</h1>
                <p class="lede">{{ __('ui.hero_text') }}</p>
                <div class="hero-actions"><a class="button primary" href="#start">{{ __('ui.cta') }}</a><a class="button ghost" href="#services">{{ __('ui.hero_secondary') }}</a></div>
                <p class="boundary">{{ __('ui.boundary') }}</p>
            </div>
            <div class="thread-card" aria-labelledby="thread-title">
                <div class="thread-top"><span id="thread-title">{{ __('ui.route.label') }}</span><img src="/assets/brand-mark.svg" alt="" width="60" height="60"></div>
                <ol>
                    <li><span>01</span><div><strong>{{ __('ui.route.one') }}</strong><small>{{ __('ui.route.one_note') }}</small></div></li>
                    <li><span>02</span><div><strong>{{ __('ui.route.two') }}</strong><small>{{ __('ui.route.two_note') }}</small></div></li>
                    <li><span>03</span><div><strong>{{ __('ui.route.three') }}</strong><small>{{ __('ui.route.three_note') }}</small></div></li>
                </ol>
            </div>
        </div>
    </section>

    <section class="section" id="services">
        <div class="shell">
            <p class="kicker">{{ __('ui.services_kicker') }}</p><h2>{{ __('ui.services_title') }}</h2>
            <div class="service-grid">
                @foreach(['support','home','opg'] as $index => $service)
                    <article class="service-card"><span class="service-index">0{{ $index + 1 }}</span><div class="service-icon"><img src="/assets/icon-{{ $service }}.svg" alt="" width="48" height="48"></div><h3>{{ __('ui.services.'.$service.'.title') }}</h3><p>{{ __('ui.services.'.$service.'.text') }}</p>@if($service === 'home')<small class="tag">{{ __('ui.scope_tehran') }}</small>@endif @if($service === 'opg')<small class="tag">{{ __('ui.licensed') }}</small>@endif</article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section path-section" id="path">
        <div class="shell path-grid">
            <div><p class="kicker">{{ __('ui.path_kicker') }}</p><h2>{{ __('ui.path_title') }}</h2><p class="wide-copy">{{ __('ui.path_text') }}</p></div>
            <ol class="handoffs">@foreach(__('ui.steps') as $index => $step)<li><span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><strong>{{ $step }}</strong></li>@endforeach</ol>
        </div>
    </section>

    <section class="section trust-section" id="trust">
        <div class="shell trust-grid"><div class="shield-art" aria-hidden="true"><img src="/assets/brand-mark.svg" alt="" width="120" height="120"><i></i><b></b></div><div><p class="kicker">OPG / PRIVACY</p><h2>{{ __('ui.trust_title') }}</h2><p class="wide-copy">{{ __('ui.trust_text') }}</p><p class="boundary strong">{{ __('ui.boundary') }}</p></div></div>
    </section>

    <section class="section start-section" id="start">
        <div class="shell start-card"><div><p class="kicker">24 / 7</p><h2>{{ __('ui.form_title') }}</h2><p>{{ __('ui.form_text') }}</p></div><div class="launch-state" role="status"><span aria-hidden="true">●</span><div><strong>{{ __('ui.coming') }}</strong><small>{{ __('ui.coming_text') }}</small></div></div></div>
    </section>

    <section class="section faq" id="faq"><div class="shell"><h2>{{ __('ui.faq_title') }}</h2><div class="faq-list">@foreach(__('ui.faqs') as $item)<details><summary>{{ $item['q'] }}<span aria-hidden="true">+</span></summary><p>{{ $item['a'] }}</p></details>@endforeach</div></div></section>
</main>

<footer><div class="shell footer-row"><div class="brand"><img src="/assets/brand-mark.svg" alt="" width="46" height="46"><span><strong>{{ app()->getLocale() === 'en' ? 'Royadarman' : 'رویا درمان' }}</strong><small>{{ __('ui.footer') }}</small></span></div><p>© {{ now()->setTimezone('Asia/Tehran')->format('Y') }}</p></div></footer>
</body>
</html>
