<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale,['fa','ar'],true)?'rtl':'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#2947a3">
    <meta name="description" content="{{ $seo?->meta_description ?: ($translation->excerpt ?: Str::limit(strip_tags($translation->body),160)) }}"><meta name="robots" content="{{ $robots }}">
    <title>{{ $seo?->seo_title ?: $translation->title }}</title><link rel="canonical" href="{{ $canonical }}">
    @foreach($alternates as $lang=>$href)<link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">@endforeach<link rel="alternate" hreflang="x-default" href="{{ $alternates['fa'] ?? $canonical }}">
    <meta property="og:type" content="article"><meta property="og:title" content="{{ $seo?->og_title ?: $translation->title }}"><meta property="og:description" content="{{ $seo?->og_description ?: ($translation->excerpt ?: Str::limit(strip_tags($translation->body),160)) }}"><meta property="og:url" content="{{ $canonical }}"><meta property="og:image" content="{{ $ogImage ?: url('/assets/photos/coord.jpg') }}"><meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/font.css?v=20260915"><link rel="stylesheet" href="/assets/site.css?v=20260915">
    @if($schema)<script type="application/ld+json">{!! json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>@endif
</head>
<body>
@if(!empty($isPreview))<div class="preview-banner" role="status">{{ __('ui.admin.preview') }}</div>@endif
@include('public.partials.header')
<main id="main">
    <section class="content-hero compact">
        <div class="shell">
            <p class="eyebrow"><a href="{{ $locale==='fa'?url('/blog'):url('/'.$locale.'/blog') }}">{{ __('ui.blog_index_heading') }}</a></p>
            <h1>{{ $translation->title }}</h1>
            <p class="article-meta">{{ __('ui.blog_org_author') }} · {{ $post->published_at?->format('Y-m-d') }}</p>
        </div>
    </section>
    <section class="section">
        <div class="shell">
            <figure class="photo-frame">
                <picture>
                    <source srcset="/assets/photos/coord.webp" type="image/webp">
                    <img src="/assets/photos/coord.jpg" width="1600" height="880" alt="{{ __('site.photos.coord.alt') }}" loading="lazy">
                </picture>
                <figcaption>{{ __('site.photos.coord.caption') }}</figcaption>
            </figure>
        </div>
    </section>
    <section class="section white"><article class="shell prose article-body">{!! $translation->sanitized_body !!}</article></section>
</main>
@include('public.partials.footer')
</body></html>
