<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale, ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2947a3">
    <meta name="description" content="{{ $seo?->meta_description ?: Str::limit(strip_tags($translation->body), 160) }}">
    <meta name="robots" content="{{ $robots }}">
    <title>{{ $seo?->seo_title ?: $translation->title }}</title>
    <link rel="canonical" href="{{ $canonical }}">
    @foreach($alternates as $lang => $href)<link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">@endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternates['fa'] ?? $canonical }}">
    @if($ogImage)
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $seo?->og_title ?: $translation->title }}">
    <meta property="og:description" content="{{ $seo?->og_description ?: Str::limit(strip_tags($translation->body), 160) }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', $locale) }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo?->og_title ?: $translation->title }}">
    <meta name="twitter:description" content="{{ $seo?->og_description ?: Str::limit(strip_tags($translation->body), 160) }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/site.css?v=20260910">
    @if($schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
</head>
<body>
@if(! empty($isPreview))
    <div class="preview-banner" role="status" style="background:#b45309;color:#fff;padding:8px 16px;text-align:center;font-weight:600">
        {{ __('ui.admin.preview') }}
    </div>
@endif
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<header class="site-header">
    <div class="shell header-row">
        <a class="brand" href="/{{ $locale }}/" aria-label="{{ __('ui.meta_title') }}">
            <img src="/assets/brand-mark.svg" alt="" width="54" height="54">
            <span><strong>{{ $locale === 'en' ? 'Royadarman' : 'رویا درمان' }}</strong><small>{{ __('ui.brand_subtitle') }}</small></span>
        </a>
        <div class="header-actions">
            <details class="language"><summary>{{ strtoupper($locale) }}<span aria-hidden="true">ℇ</span></summary><div>@foreach(['fa' => 'فارسی','ar' => 'العربية','en' => 'English'] as $code => $label)<a lang="{{ $code }}" dir="{{ $code === 'en' ? 'ltr' : 'rtl' }}" hreflang="{{ $code }}" href="/{{ $code }}/" @if($code === $locale) aria-current="page" @endif>{{ $label }}</a>@endforeach</div></details>
        </div>
    </div>
</header>

<main id="main">
    <article class="shell blog-article">
        <a class="back-link" href="/{{ $locale }}/" rel="nofollow">← {{ __('ui.brand_subtitle') }}</a>
        <h1>{{ $translation->title }}</h1>
        @if($post->author)<p class="byline"><small>{{ $post->published_at?->format('Y-m-d') }}</small></p>@endif
        <div class="article-body">
            {!! $translation->sanitized_body ?: $translation->body !!}
        </div>
    </article>
</main>

<footer><div class="shell footer-row"><div class="brand"><img src="/assets/brand-mark.svg" alt="" width="46" height="46"><span><strong>{{ $locale === 'en' ? 'Royadarman' : 'رویا درمان' }}</strong><small>{{ __('ui.footer') }}</small></span></div><p>© {{ now()->setTimezone('Asia/Tehran')->format('Y') }}</p></div></footer>
</body>
</html>
