@php($locale=app()->getLocale())
<details class="contact-launcher">
    <summary>{{ __('site.contact_launcher.open') }}</summary>
    <nav aria-label="{{ __('site.contact_launcher.open') }}">
        <a href="tel:+989122701201" dir="ltr" aria-label="{{ __('site.contact_launcher.call') }}: +98 912 270 1201">{{ __('site.contact_launcher.call') }} · <bdi>0912 270 1201</bdi></a>
        <a href="{{ \App\Support\PublicUrl::to('contact', $locale) }}">{{ __('site.contact_launcher.contact') }}</a>
        <p>{{ __('site.contact_launcher.privacy') }}</p>
    </nav>
</details>
