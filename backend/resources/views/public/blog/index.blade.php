<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale,['fa','ar'],true)?'rtl':'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#2947a3"><meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="description" content="{{ __('ui.blog_index_description') }}">
    <title>{{ __('ui.blog_index_title') }}</title>
    <link rel="canonical" href="{{ $canonical }}">
    @foreach($alternates as $lang=>$href)<link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">@endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternates['fa'] }}">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/font.css?v=20260912"><link rel="stylesheet" href="/assets/site.css?v=20260912">
</head>
<body>
@include('public.partials.header')
<main id="main">
    <section class="content-hero"><div class="shell"><p class="eyebrow">{{ __('site.brand') }}</p><h1>{{ __('ui.blog_index_heading') }}</h1><p class="hero-lead">{{ __('ui.blog_index_description') }}</p></div></section>
    <section class="section white"><div class="shell article-list">
        @forelse($translations as $translation)
            @php $post=$translation->post; $href=$locale==='fa'?url('/blog/'.$translation->slug):url('/'.$locale.'/blog/'.$translation->slug); @endphp
            <article class="article-card"><div><p class="article-meta">{{ $post->published_at?->format('Y-m-d') }}</p><h2><a href="{{ $href }}">{{ $translation->title }}</a></h2>@if($translation->excerpt)<p>{{ $translation->excerpt }}</p>@endif</div><a class="text-link" href="{{ $href }}">{{ __('ui.blog_read_more') }} <span>←</span></a></article>
        @empty
            <div class="empty-state">{{ __('ui.blog_index_empty') }}</div>
        @endforelse
        @if($translations->hasPages())
            <nav class="pagination" aria-label="{{ __('ui.pagination') }}">@if(!$translations->onFirstPage())<a href="{{ $translations->previousPageUrl() }}" rel="prev">←</a>@endif<span>{{ $translations->currentPage() }} / {{ $translations->lastPage() }}</span>@if($translations->hasMorePages())<a href="{{ $translations->nextPageUrl() }}" rel="next">→</a>@endif</nav>
        @endif
    </div></section>
</main>
@include('public.partials.footer')
</body></html>
