<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale,['fa','ar'],true)?'rtl':'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#2947a3">
    <meta name="description" content="{{ $seo?->meta_description ?: Str::limit(strip_tags($translation->body),160) }}"><meta name="robots" content="{{ $robots }}">
    <title>{{ $seo?->seo_title ?: $translation->title }}</title><link rel="canonical" href="{{ $canonical }}">
    @foreach($alternates as $lang=>$href)<link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">@endforeach<link rel="alternate" hreflang="x-default" href="{{ $alternates['fa'] ?? $canonical }}">
    @if($ogImage)<meta property="og:type" content="article"><meta property="og:title" content="{{ $seo?->og_title ?: $translation->title }}"><meta property="og:description" content="{{ $seo?->og_description ?: Str::limit(strip_tags($translation->body),160) }}"><meta property="og:image" content="{{ $ogImage }}"><meta property="og:url" content="{{ $canonical }}"><meta name="twitter:card" content="summary_large_image">@endif
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/font.css?v=20260912"><link rel="stylesheet" href="/assets/site.css?v=20260912">
    @if($schema)<script type="application/ld+json">{!! json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>@endif
</head>
<body>
@if(!empty($isPreview))<div class="preview-banner" role="status">{{ __('ui.admin.preview') }}</div>@endif
@include('public.partials.header')
<main id="main"><section class="content-hero compact"><div class="shell"><p class="eyebrow">{{ __('ui.blog_index_heading') }}</p><h1>{{ $translation->title }}</h1><p class="article-meta">{{ $post->published_at?->format('Y-m-d') }}</p></div></section><section class="section white"><article class="shell prose article-body">{!! $translation->sanitized_body !!}</article></section></main>
@include('public.partials.footer')
</body></html>
