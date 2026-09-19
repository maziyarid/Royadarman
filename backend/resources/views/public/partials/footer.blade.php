@php $locale=app()->getLocale(); $pub=fn(string $name):string=>\App\Support\PublicUrl::to($name, $locale); @endphp
<footer class="site-footer">
    <div class="shell footer-grid">
        <div>
            <a class="brand footer-brand" href="{{ $pub('home') }}">
                <img src="/assets/brand-mark.svg" width="46" height="46" alt="">
                <span><strong>{{ __('site.brand') }}</strong><small>{{ __('site.tagline') }}</small></span>
            </a>
            <p>{{ __('site.footer_intro') }}</p>
        </div>
        <div>
            <strong>{{ __('site.footer_services') }}</strong>
            <a href="{{ $pub('opg') }}">{{ __('site.links.opg') }}</a>
            <a href="{{ $pub('home-dentistry') }}">{{ __('site.links.home') }}</a>
            <a href="{{ $pub('referrals') }}">{{ __('site.links.referral') }}</a>
        </div>
        <div>
            <strong>{{ __('site.footer_company') }}</strong>
            <a href="{{ $pub('about') }}">{{ __('site.nav.about') }}</a>
            <a href="{{ $pub('how') }}">{{ __('site.nav.how') }}</a>
            <a href="{{ $pub('contact') }}">{{ __('site.nav.contact') }}</a>
            <a href="{{ $pub('blog.index') }}">{{ __('ui.blog_index_heading') }}</a>
        </div>
        <div>
            <strong>{{ __('site.footer_trust') }}</strong>
            <a href="{{ $pub('privacy') }}">{{ __('site.links.privacy') }}</a>
            <a href="{{ $pub('faq') }}">{{ __('site.nav.faq') }}</a>
            @unless(app()->environment('production'))
                <a href="{{ url('/pres') }}">{{ __('ui.pres.title') }}</a>
            @endunless
            <a href="{{ route('login',['locale'=>$locale]) }}">{{ __('site.links.login') }}</a>
        </div>
    </div>
    <div class="shell footer-bottom">
        <span>© {{ now()->setTimezone('Asia/Tehran')->format('Y') }} {{ __('site.brand') }}</span>
        <span>{{ __('ui.boundary') }}</span>
    </div>
</footer>
