@php
    $phone = (string) config('royadarman.public_contact.phone', '');
    $channels = [];
    foreach (['whatsapp' => 'wa.me', 'instagram' => 'instagram.com', 'linkedin' => 'linkedin.com'] as $name => $domain) {
        $value = config('royadarman.public_contact.'.$name);
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            continue;
        }
        $parts = parse_url($value);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') === 'https' && ($host === $domain || str_ends_with($host, '.'.$domain)) && empty($parts['user']) && empty($parts['pass'])) {
            $channels[$name] = $value;
        }
    }
@endphp
<details class="contact-launcher">
    <summary>{{ __('site.contact_launcher.open') }}</summary>
    <nav aria-label="{{ __('site.contact_launcher.open') }}">
        @if(preg_match('/^09\d{9}$/', $phone))
            <a href="tel:+98{{ substr($phone, 1) }}">{{ __('site.contact_launcher.phone') }} <bdi>{{ $phone }}</bdi></a>
        @endif
        @foreach($channels as $name => $url)
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ __('site.contact_launcher.'.$name) }}</a>
        @endforeach
        <a href="{{ \App\Support\PublicUrl::to('contact', app()->getLocale()) }}">{{ __('site.contact_launcher.contact') }}</a>
        <p>{{ __('site.contact_launcher.privacy') }}</p>
    </nav>
</details>
