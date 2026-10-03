@php $locale=app()->getLocale(); $pub=fn(string $name):string=>$locale==='fa'?route($name.'.fa'):route($name,['locale'=>$locale]); @endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale,['fa','ar'],true)?'rtl':'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#2947a3">
<meta name="description" content="{{ __('public_home.meta_description') }}">
<meta name="robots" content="index,follow,max-image-preview:large">
<title>{{ __('public_home.meta_title') }}</title>
<link rel="canonical" href="{{ $pub('public.home') }}">
@foreach(['fa','ar','en'] as $l)<link rel="alternate" hreflang="{{ $l }}" href="{{ $l==='fa'?route('public.home.fa'):route('public.home',['locale'=>$l]) }}">@endforeach
<link rel="alternate" hreflang="x-default" href="{{ route('public.home.fa') }}">
<meta property="og:title" content="{{ __('public_home.meta_title') }}">
<meta property="og:description" content="{{ __('public_home.meta_description') }}">
<meta property="og:image" content="{{ url('/assets/brand-mark.svg') }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/font.css?v=20260916">
<link rel="stylesheet" href="/assets/site.css?v=20260929-contact-1">
<link rel="stylesheet" href="/assets/public-home.css?v=20261003">
<script src="/assets/site.js?v=20260917" defer></script>
@php($orgSchema=['@context'=>'https://schema.org','@type'=>'Organization','name'=>'Royadarman','url'=>route('public.home.fa'),'description'=>__('public_home.meta_description')])
<script type="application/ld+json">{!! json_encode($orgSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
</head>
<body>
@include('public.partials.header')
<main id="main" class="public-home">
<section class="public-home-hero" aria-labelledby="public-home-title">
    <div class="shell public-home-hero-grid">
        <div class="public-home-hero-copy">
            <p class="public-home-eyebrow">{{ __('public_home.eyebrow') }}</p>
            <h1 id="public-home-title">{{ __('public_home.hero_title') }}</h1>
            <p class="public-home-lead">{{ __('public_home.hero_text') }}</p>
            <div class="public-home-actions">
                <a class="button primary" href="{{ route('login', ['locale' => $locale]) }}">{{ __('public_home.sign_in') }}</a>
                <a class="button ghost" href="{{ $pub('public.services') }}">{{ __('public_home.browse_services') }}</a>
            </div>
            <form class="public-home-search" method="get" action="{{ $pub('public.services') }}" role="search">
                <label for="need-q">{{ __('public_home.search_label') }}</label>
                <div><input id="need-q" name="q" type="search" autocomplete="off" placeholder="{{ __('public_home.search_placeholder') }}"><button class="button ghost" type="submit">{{ __('public_home.search_submit') }}</button></div>
            </form>
            @if(!config('royadarman.intake_enabled'))
                <p class="public-home-intake" data-home-intake="paused">{{ __('public_home.intake_paused') }}</p>
            @else
                <p class="public-home-intake">{{ __('public_home.request_available') }}</p>
            @endif
        </div>
        <aside class="public-home-path" aria-labelledby="public-home-path-title">
            <div class="public-home-brand"><img class="public-home-brand-mark" src="/assets/brand-mark.svg" width="96" height="96" alt="{{ __('public_home.brand_alt') }}" fetchpriority="high"><span>{{ __('site.brand') }}<small>{{ __('site.tagline') }}</small></span></div>
            <h2 id="public-home-path-title">{{ __('public_home.path_title') }}</h2>
            <p>{{ __('public_home.path_intro') }}</p>
            <ol>
                @foreach(__('public_home.path_steps') as $i => $step)
                    <li><span aria-hidden="true">{{ $i + 1 }}</span><div><strong>{{ $step['title'] }}</strong><p>{{ $step['text'] }}</p></div></li>
                @endforeach
            </ol>
        </aside>
    </div>
</section>
<section class="public-home-section" aria-labelledby="public-home-services-title">
    <div class="shell">
        <div class="public-home-section-heading"><p class="public-home-eyebrow">{{ __('public_home.services_eyebrow') }}</p><h2 id="public-home-services-title">{{ __('public_home.services_title') }}</h2><p>{{ __('public_home.services_text') }}</p></div>
        <div class="public-home-services">
            @foreach(['opg' => ['public.opg', 'icon-opg.svg', 'OPG opg پانورامیک review'], 'home' => ['public.home-dentistry', 'icon-home.svg', 'home dentistry منزل خانه'], 'referral' => ['public.referrals', 'icon-support.svg', 'referral clinic کلینیک ارجاع guidance']] as $key => [$routeName, $icon, $search])
                <a class="public-home-service" href="{{ $pub($routeName) }}" data-service-card data-search="{{ $search }}">
                    <img src="/assets/{{ $icon }}" width="48" height="48" alt="" loading="lazy">
                    <h3>{{ __('public_home.services.'.$key.'.title') }}</h3>
                    <p>{{ __('public_home.services.'.$key.'.text') }}</p>
                    <span>{{ __('public_home.service_link') }} <span class="public-home-arrow" aria-hidden="true">←</span></span>
                </a>
            @endforeach
        </div>
    </div>
</section>
<section class="public-home-section public-home-discovery-intro" aria-labelledby="public-home-discovery-title">
    <div class="shell public-home-discovery-grid">
        <div><p class="public-home-eyebrow">{{ __('public_home.discovery_eyebrow') }}</p><h2 id="public-home-discovery-title">{{ __('public_home.discovery_title') }}</h2><p>{{ __('public_home.discovery_text') }}</p><a class="button primary" data-home-discovery-link href="#public-home-clinics">{{ __('public_home.discovery_link') }}</a></div>
        <div class="public-home-discovery-note"><p>{{ __('public_home.discovery_boundary') }}</p><a class="public-home-text-link" href="{{ $pub('public.referrals') }}">{{ __('public_home.services.referral.title') }} <span class="public-home-arrow" aria-hidden="true">←</span></a></div>
    </div>
</section>
<div id="public-home-clinics" class="public-home-map">
    @include('public.partials.discovery-map')
</div>
<section class="public-home-section" aria-labelledby="public-home-areas-title">
    <div class="shell public-home-areas">
        <div><h2 id="public-home-areas-title">{{ __('public_home.areas_title') }}</h2><p>{{ __('public_home.areas_text') }}</p></div>
        <div class="public-home-area-links">
            @foreach(config('royadarman.tehran_neighborhoods', []) as $n)
                <a href="{{ $pub('public.services') }}?q={{ urlencode($n[$locale] ?? $n['en']) }}" data-area="{{ $n['id'] }}" data-search="{{ $n['fa'] }} {{ $n['en'] }} {{ $n['ar'] }} {{ $n['area'] }}">{{ $n[$locale] ?? $n['en'] }}</a>
            @endforeach
        </div>
    </div>
</section>
<section class="public-home-section" aria-labelledby="public-home-how-title">
    <div class="shell public-home-information">
        <article class="public-home-info-card"><h2 id="public-home-how-title">{{ __('public_home.how_title') }}</h2><p>{{ __('public_home.how_text') }}</p><a class="public-home-text-link" href="{{ $pub('public.how') }}">{{ __('public_home.how_link') }} <span class="public-home-arrow" aria-hidden="true">←</span></a></article>
        <article class="public-home-info-card public-home-privacy"><h2>{{ __('public_home.privacy_title') }}</h2><p>{{ __('public_home.privacy_text') }}</p><p class="public-home-boundary">{{ __('ui.boundary') }}</p><a class="public-home-text-link" href="{{ $pub('public.privacy') }}">{{ __('public_home.privacy_link') }} <span class="public-home-arrow" aria-hidden="true">←</span></a></article>
    </div>
</section>
<section class="public-home-section" aria-labelledby="public-home-faq-title">
    <div class="shell public-home-faq"><div><h2 id="public-home-faq-title">{{ __('public_home.faq_title') }}</h2><a class="public-home-text-link" href="{{ $pub('public.faq') }}">{{ __('public_home.faq_link') }} <span class="public-home-arrow" aria-hidden="true">←</span></a></div><div class="public-home-faq-list">
        @foreach(array_slice(__('site.faqs'), 0, 3) as $item)
            <details><summary>{{ $item['q'] }}<span aria-hidden="true">+</span></summary><p>{{ $item['a'] }}</p></details>
        @endforeach
    </div></div>
</section>
<section class="public-home-section public-home-final" aria-labelledby="public-home-contact-title">
    <div class="shell public-home-contact"><div><h2 id="public-home-contact-title">{{ __('public_home.contact_title') }}</h2><p>{{ __('public_home.contact_text') }}</p></div><div class="public-home-actions"><a class="button primary" href="{{ route('login', ['locale' => $locale]) }}">{{ __('public_home.sign_in') }}</a><a class="button ghost" href="{{ $pub('public.contact') }}">{{ __('public_home.contact_link') }}</a></div></div>
</section>
</main>
@include('public.partials.footer')
</body>
</html>
