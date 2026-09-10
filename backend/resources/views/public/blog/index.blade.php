<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale, ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2947a3">
    <meta name="description" content="{{ __('ui.blog_index_description') }}">
    <meta name="robots" content="index, follow">
    <title>{{ __('ui.blog_index_title') }}</title>
    <link rel="canonical" href="{{ $canonical }}">
    @foreach(['fa','ar','en'] as $lang)<link rel="alternate" hreflang="{{ $lang }}" href="{{ url('/'.$lang.'/blog/') }}">@endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('/fa/blog/') }}">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/site.css?v=20260910">
</head>
<body>
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<header class="site-header">
    <div class="shell header-row">
        <a class="brand" href="/{{ $locale }}/" aria-label="{{ __('ui.meta_title') }}">
            <img src="/assets/brand-mark.svg" alt="" width="54" height="54">
            <span><strong>{{ $locale === 'en' ? 'Royadarman' : 'رویادرمان' }}</strong><small>{{ __('ui.brand_subtitle') }}</small></span>
        </a>
        <div class="header-actions">
            <details class="language"><summary>{{ strtoupper($locale) }}<span aria-hidden="true">⇄</span></summary><div>@foreach(['fa' => 'فارسی','ar' => 'العربية','en' => 'English'] as $code => $label)<a lang="{{ $code }}" dir="{{ $code === 'en' ? 'ltr' : 'rtl' }}" hreflang="{{ $code }}" href="/{{ $code }}/blog/" @if($code === $locale) aria-current="page" @endif>{{ $label }}</a>@endforeach</div></details>
        </div>
    </div>
</header>

<main id="main">
    <section class="blog-index-head">
        <div class="shell">
            <a class="back-link" href="/{{ $locale }}/" rel="nofollow">← {{ __('ui.brand_subtitle') }}</a>
            <h1>{{ __('ui.blog_index_heading') }}</h1>
            <p class="lede">{{ __('ui.blog_index_description') }}</p>
        </div>
    </section>

    <section class="blog-list">
        <div class="shell">
            @forelse($translations as $translation)
                @php $post = $translation->post; @endphp
                <article class="blog-list-item">
                    <h2><a href="/{{ $locale }}/blog/{{ $translation->slug }}">{{ $translation->title }}</a></h2>
                    @if($post->author)
                        <p class="byline"><small>{{ $post->published_at?->format('Y-m-d') }}</small></p>
                    @endif
                    @if($translation->excerpt)
                        <p>{{ $translation->excerpt }}</p>
                    @endif
                    <a class="read-more" href="/{{ $locale }}/blog/{{ $translation->slug }}">{{ __('ui.blog_read_more') }} →</a>
                </article>
            @empty
                <p class="empty-state">{{ __('ui.blog_index_empty') }}</p>
            @endforelse
        </div>
    </section>

    @if($translations->hasPages())
    <nav class="pagination shell" aria-label="{{ __('ui.pagination') }}">
        @if($translations->onFirstPage())
            <span class="disabled" aria-disabled="true">←</span>
        @else
            <a href="{{ $translations->previousPageUrl() }}" rel="prev">←</a>
        @endif
        <span>{{ $translations->currentPage() }} / {{ $translations->lastPage() }}</span>
        @if($translations->hasMorePages())
            <a href="{{ $translations->nextPageUrl() }}" rel="next">→</a>
        @else
            <span class="disabled" aria-disabled="true">→</span>
        @endif
    </nav>
    @endif
</main>

<footer><div class="shell footer-row"><div class="brand"><img src="/assets/brand-mark.svg" alt="" width="46" height="46"><span><strong>{{ $locale === 'en' ? 'Royadarman' : 'رویادرمان' }}</strong><small>{{ __('ui.footer') }}</small></span></div><p>© {{ now()->setTimezone('Asia/Tehran')->format('Y') }}</p></div></footer>
</body>
</html>
