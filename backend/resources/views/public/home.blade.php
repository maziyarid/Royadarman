@php $locale=app()->getLocale(); $pub=fn(string $name):string=>$locale==='fa'?route($name.'.fa'):route($name,['locale'=>$locale]); @endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale,['fa','ar'],true)?'rtl':'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#2947a3">
<meta name="description" content="{{ __('ui.meta_description') }}">
<meta name="robots" content="index,follow,max-image-preview:large">
<title>{{ __('ui.meta_title') }}</title>
<link rel="canonical" href="{{ $pub('public.home') }}">
@foreach(['fa','ar','en'] as $l)<link rel="alternate" hreflang="{{ $l }}" href="{{ $l==='fa'?route('public.home.fa'):route('public.home',['locale'=>$l]) }}">@endforeach
<link rel="alternate" hreflang="x-default" href="{{ route('public.home.fa') }}">
<meta property="og:title" content="{{ __('ui.meta_title') }}">
<meta property="og:description" content="{{ __('ui.meta_description') }}">
<meta property="og:image" content="{{ url('/assets/photos/tehran.jpg') }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/font.css?v=20260916">
<link rel="stylesheet" href="/assets/site.css?v=20260916">
<script src="/assets/site.js?v=20260916" defer></script>
@php($orgSchema=['@context'=>'https://schema.org','@type'=>'Organization','name'=>'Royadarman','url'=>route('public.home.fa'),'description'=>__('ui.meta_description')])
<script type="application/ld+json">{!! json_encode($orgSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
</head>
<body>
@include('public.partials.header')
<main id="main">
<section class="hero-home">
    <div class="shell hero-home-grid">
        <div>
            <p class="hero-kicker">{{ __('site.home.hero_kicker') }}</p>
            <h1>{{ __('site.home.hero_title') }}</h1>
            <p class="hero-lead">{{ __('site.home.hero_text') }}</p>
            <form class="need-search" method="get" action="{{ $pub('public.services') }}" role="search">
                <label class="visually-hidden" for="need-q">{{ __('site.home.search_placeholder') }}</label>
                <input id="need-q" name="q" type="search" autocomplete="off" placeholder="{{ __('site.home.search_placeholder') }}">
                <button class="button primary" type="submit">{{ __('site.cta') }}</button>
            </form>
            <div class="hero-actions">
                <a class="button primary" href="{{ route('login',['locale'=>$locale]) }}">{{ __('site.home.hero_primary') }}</a>
                <a class="button ghost" href="{{ $pub('public.how') }}">{{ __('site.home.hero_secondary') }}</a>
            </div>
            <p class="boundary">{{ __('ui.boundary') }}</p>
        </div>
        <figure class="hero-photo">
            <picture>
                <source srcset="/assets/photos/tehran.webp" type="image/webp">
                <img src="/assets/photos/tehran.jpg" width="1600" height="880" alt="{{ __('site.home.photo_alt') }}" fetchpriority="high">
            </picture>
            <figcaption>{{ __('site.home.photo_caption') }}</figcaption>
        </figure>
    </div>
</section>
<section class="section white">
    <div class="shell">
        <div class="section-heading">
            <h2>{{ __('site.home.services_title') }}</h2>
            <p>{{ __('site.home.services_intro') }}</p>
        </div>
        <div class="service-links">
            <a class="service-link photo-card" href="{{ $pub('public.opg') }}">
                <img src="/assets/photos/opg.jpg" width="640" height="360" alt="" loading="lazy">
                <span><strong>{{ __('site.home.services.opg.title') }}</strong><small>{{ __('site.home.services.opg.text') }}</small></span>
                <b>←</b>
            </a>
            <a class="service-link photo-card" href="{{ $pub('public.home-dentistry') }}">
                <img src="/assets/photos/home.jpg" width="640" height="360" alt="" loading="lazy">
                <span><strong>{{ __('site.home.services.home.title') }}</strong><small>{{ __('site.home.services.home.text') }}</small></span>
                <b>←</b>
            </a>
            <a class="service-link photo-card" href="{{ $pub('public.referrals') }}">
                <img src="/assets/photos/coord.jpg" width="640" height="360" alt="" loading="lazy">
                <span><strong>{{ __('site.home.services.referral.title') }}</strong><small>{{ __('site.home.services.referral.text') }}</small></span>
                <b>←</b>
            </a>
        </div>
        <div class="center-action"><a class="text-link" href="{{ $pub('public.services') }}">{{ __('site.home.all_services') }} <span>←</span></a></div>
    </div>
</section>
<section class="section">
    <div class="shell split coverage-split">
        <div>
            <h2>{{ __('site.home.coverage_title') }}</h2>
            <p>{{ __('site.home.coverage_text') }}</p>
            <div class="coverage-chips">
                @foreach(__('site.home.neighborhoods') as $area)
                    <a href="{{ $pub('public.home-dentistry') }}" data-area="{{ $area }}">{{ $area }}</a>
                @endforeach
            </div>
            <a class="button ghost" href="{{ $pub('public.home-dentistry') }}">{{ __('site.home.coverage_cta') }}</a>
        </div>
        <figure class="hero-photo">
            <img src="/assets/photos/tehran.jpg" width="1200" height="660" alt="{{ __('site.home.photo_alt') }}" loading="lazy">
        </figure>
    </div>
</section>
<section class="section lapis">
    <div class="shell split">
        <div>
            <h2>{{ __('site.home.process_title') }}</h2>
            <p>{{ __('site.home.process_text') }}</p>
            <a class="button light" href="{{ $pub('public.how') }}">{{ __('site.home.process_link') }}</a>
        </div>
        <ol class="process-list">
            @foreach(__('site.home.process_steps') as $i=>$step)
                <li>
                    <span>{{ $i+1 }}</span>
                    <div>
                        <strong>{{ $step['title'] }}</strong>
                        <p>{{ $step['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
<section class="section">
    <div class="shell trust-layout">
        <div class="trust-art"><img src="/assets/brand-mark.svg" width="96" height="96" alt=""></div>
        <div>
            <h2>{{ __('site.home.trust_title') }}</h2>
            <p class="large-copy">{{ __('site.home.trust_text') }}</p>
            <div class="check-list">
                @foreach(__('site.home.trust_points') as $point)<p><span>✓</span>{{ $point }}</p>@endforeach
            </div>
            <a class="text-link" href="{{ $pub('public.privacy') }}">{{ __('site.home.trust_link') }} <span>←</span></a>
        </div>
    </div>
</section>
<section class="section white">
    <div class="shell cta-band">
        <div>
            <h2>{{ __('site.home.cta_title') }}</h2>
            <p>{{ __('site.home.cta_text') }}</p>
        </div>
        <div class="cta-actions">
            <a class="button primary" href="{{ route('login',['locale'=>$locale]) }}">{{ __('site.cta') }}</a>
            <a class="button ghost" href="{{ $pub('public.contact') }}">{{ __('site.nav.contact') }}</a>
        </div>
    </div>
</section>
<section class="section">
    <div class="shell faq-preview">
        <div>
            <h2>{{ __('site.home.faq_title') }}</h2>
            <p>{{ __('site.home.faq_intro') }}</p>
            <a class="text-link" href="{{ $pub('public.faq') }}">{{ __('site.home.faq_link') }} <span>←</span></a>
        </div>
        <div class="faq-list">
            @foreach(array_slice(__('site.faqs'),0,3) as $item)
                <details>
                    <summary>{{ $item['q'] }}<span>+</span></summary>
                    <p>{{ $item['a'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
</main>
@include('public.partials.footer')
</body>
</html>
