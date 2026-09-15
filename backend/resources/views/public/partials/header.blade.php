@php
    $locale = app()->getLocale();
    $pageKey = $pageKey ?? \App\Support\PublicUrl::currentKey();
    $pub = fn (string $name, ?string $l = null): string => \App\Support\PublicUrl::to($name, $l ?? $locale);
@endphp
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<header class="site-header">
    <div class="shell header-row">
        <a class="brand" href="{{ $pub('home') }}">
            <img src="/assets/brand-mark.svg" width="48" height="48" alt="">
            <span><strong>{{ __('site.brand') }}</strong><small>{{ __('site.tagline') }}</small></span>
        </a>
        <nav class="desktop-nav" aria-label="{{ __('site.nav_label') }}">
            <a href="{{ $pub('services') }}">{{ __('site.nav.services') }}</a>
            <a href="{{ $pub('how') }}">{{ __('site.nav.how') }}</a>
            <a href="{{ $pub('about') }}">{{ __('site.nav.about') }}</a>
            <a href="{{ $pub('faq') }}">{{ __('site.nav.faq') }}</a>
            <a href="{{ $pub('contact') }}">{{ __('site.nav.contact') }}</a>
            <a href="{{ $pub('blog.index') }}">{{ __('ui.blog_index_heading') }}</a>
        </nav>
        <div class="header-actions">
            <details class="language">
                <summary>{{ strtoupper($locale) }}<span>⌄</span></summary>
                <div>
                    @foreach(['fa' => 'فارسی', 'ar' => 'العربية', 'en' => 'English'] as $code => $label)
                        <a href="{{ $pub($pageKey, $code) }}" lang="{{ $code }}" dir="{{ $code === 'en' ? 'ltr' : 'rtl' }}" @if($code === $locale) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </details>
            <a class="button primary desktop-cta" href="{{ route('login', ['locale' => $locale]) }}">{{ __('site.cta') }}</a>
            <details class="mobile-nav">
                <summary aria-label="{{ __('site.menu') }}"><span></span><span></span><span></span></summary>
                <div class="mobile-panel">
                    <a href="{{ $pub('services') }}">{{ __('site.nav.services') }}</a>
                    <a href="{{ $pub('how') }}">{{ __('site.nav.how') }}</a>
                    <a href="{{ $pub('about') }}">{{ __('site.nav.about') }}</a>
                    <a href="{{ $pub('faq') }}">{{ __('site.nav.faq') }}</a>
                    <a href="{{ $pub('contact') }}">{{ __('site.nav.contact') }}</a>
                    <a href="{{ $pub('blog.index') }}">{{ __('ui.blog_index_heading') }}</a>
                    <a class="button primary" href="{{ route('login', ['locale' => $locale]) }}">{{ __('site.cta') }}</a>
                </div>
            </details>
        </div>
    </div>
</header>
