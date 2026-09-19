@php
    $locale = app()->getLocale();
    $pageKey = $pageKey ?? 'home';
    $pub = fn (string $name, ?string $l = null): string => \App\Support\PublicUrl::to($name, $l ?? $locale);
    $current = $canonical ?? $pub($pageKey);
    $alt = fn (string $l): string => $pub($pageKey, $l);
    $pageFaqs = $faqs ?? ($pageKey === 'faq' ? __('site.faqs') : []);
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $schemaType ?? 'WebPage',
        'name' => $metaTitle ?? $title,
        'description' => $metaDescription ?? ($excerpt ?? $lead ?? ''),
        'url' => $current,
        'inLanguage' => $locale,
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'Royadarman', 'url' => url('/')],
    ];
    if (!empty($pageFaqs) && is_array($pageFaqs)) {
        $faqSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
            ], $pageFaqs),
        ];
    }
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale, ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#2947a3">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <title>{{ $metaTitle ?? $title }}</title>
    <meta name="description" content="{{ $metaDescription ?? ($excerpt ?? $lead ?? '') }}">
    <link rel="canonical" href="{{ $current }}">
    @foreach(['fa','ar','en'] as $l)
        <link rel="alternate" hreflang="{{ $l }}" href="{{ $alt($l) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alt('fa') }}">
    <meta property="og:title" content="{{ $metaTitle ?? $title }}">
    <meta property="og:description" content="{{ $metaDescription ?? ($excerpt ?? $lead ?? '') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $current }}">
    @if(!empty($photo))
        <meta property="og:image" content="{{ url('/assets/photos/'.$photo.'.jpg') }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/font.css?v=20260915">
    <link rel="stylesheet" href="/assets/site.css?v=20260919">
    <script src="/assets/site.js?v=20260917" defer></script>
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
    @if(!empty($faqSchema))
        <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
</head>
<body>
@include('public.partials.header')
<main id="main">
    <section class="page-hero">
        <div class="shell {{ empty($photo) ? 'narrow' : '' }}">
            <p class="eyebrow">{{ __('site.brand') }}</p>
            <h1>{{ $title }}</h1>
            <p>{{ $lead ?? $excerpt ?? '' }}</p>
            @if(!empty($heroActions))
                <div class="hero-actions">
                    @foreach($heroActions as $action)
                        <a class="button {{ $action['style'] ?? 'ghost' }}" href="{{ $action['href'] }}">{{ $action['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if(!empty($photo))
        <section class="section">
            <div class="shell">
                <figure class="photo-frame">
                    <picture>
                        <source srcset="/assets/photos/{{ $photo }}.webp" type="image/webp">
                        <img src="/assets/photos/{{ $photo }}.jpg" width="1600" height="880" alt="{{ __('site.photos.'.$photo.'.alt') }}" loading="lazy">
                    </picture>
                    <figcaption>{{ __('site.photos.'.$photo.'.caption') }}</figcaption>
                </figure>
            </div>
        </section>
    @endif

    @if(!empty($serviceCards))
        <section class="section white">
            <div class="shell">
                @if(!empty($serviceQuery))
                    <p class="notice">
                        @if(!empty($serviceMatched))
                            {{ __('site.home.search_results', ['q' => $serviceQuery]) }}
                        @else
                            {{ __('site.home.search_no_match') }}
                        @endif
                    </p>
                @endif
                <div class="service-links large-services">
                    @foreach($serviceCards as $service)
                        <a class="service-link photo-card{{ !empty($service['matched']) ? ' is-match' : '' }}" href="{{ $pub($service['key']) }}" data-service-card data-search="{{ $service['title'] }} {{ $service['text'] }} {{ $service['key'] }}">
                            <img src="/assets/photos/{{ $service['photo'] }}.jpg" width="640" height="360" alt="" loading="lazy">
                            <span>
                                <strong>{{ $service['title'] }}</strong>
                                @if(!empty($service['matched']))<em class="match-flag">{{ __('site.home.search_matched') }}</em>@endif
                                <small>{{ $service['text'] }}</small>
                            </span>
                            <b>←</b>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(!empty($points))
        <section class="section white">
            <div class="shell content-section">
                <div>
                    <h2>{{ $overviewTitle ?? $title }}</h2>
                    @if(!empty($body))<p class="large-copy">{{ $body }}</p>@endif
                </div>
                <div class="check-list boxed">
                    @foreach($points as $point)
                        <p><span>✓</span>{{ $point }}</p>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @foreach($sections ?? [] as $index => $section)
        <section class="section {{ $index % 2 ? 'white' : '' }}">
            <div class="shell content-section">
                <div>
                    <h2>{{ $section['title'] }}</h2>
                    @if(!empty($section['body']))<p class="large-copy">{{ $section['body'] }}</p>@endif
                </div>
                @if(!empty($section['items']))
                    <div class="info-list">
                        @foreach($section['items'] as $item)
                            <article>
                                <span>{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <div>
                                    <strong>{{ is_array($item) ? $item['title'] : $item }}</strong>
                                    @if(is_array($item) && !empty($item['text']))<p>{{ $item['text'] }}</p>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endforeach

    @if(!empty($steps))
        <section class="section">
            <div class="shell">
                <div class="section-heading"><h2>{{ $stepsTitle }}</h2></div>
                <ol class="step-grid">
                    @foreach($steps as $step)
                        <li>
                            <span>{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <strong>{{ $step['title'] }}</strong>
                            <p>{{ $step['text'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    @if(!empty($note))
        <section class="section white">
            <div class="shell">
                <aside class="clinical-note">
                    <img src="/assets/brand-mark.svg" width="54" height="54" alt="">
                    <div>
                        <strong>{{ $noteTitle ?? __('site.trust_title') }}</strong>
                        <p>{{ $note }}</p>
                    </div>
                </aside>
            </div>
        </section>
    @endif

    @include('public.partials.discovery-map')

    @if(!empty($pageFaqs))
        <section class="section white">
            <div class="shell faq-preview">
                <div>
                    <h2>{{ $faqTitle ?? __('site.nav.faq') }}</h2>
                </div>
                <div class="faq-list">
                    @foreach($pageFaqs as $item)
                        <details>
                            <summary>{{ $item['q'] }}<span>+</span></summary>
                            <p>{{ $item['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(!empty($related))
        <section class="section">
            <div class="shell">
                <div class="section-heading"><h2>{{ __('site.related_title') }}</h2></div>
                <div class="service-links">
                    @foreach($related as $item)
                        <a class="service-link" href="{{ $pub($item['key']) }}">
                            <span>
                                <strong>{{ $item['title'] }}</strong>
                                <small>{{ $item['text'] }}</small>
                            </span>
                            <b>←</b>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section">
        <div class="shell cta-band">
            <div>
                <h2>{{ $ctaTitle ?? __('site.common_cta_title') }}</h2>
                <p>{{ $ctaText ?? __('site.common_cta_text') }}</p>
            </div>
            <div class="cta-actions">
                <a class="button primary" href="{{ route('login', ['locale' => $locale]) }}">{{ __('site.cta') }}</a>
                <a class="button ghost" href="{{ $pub('contact') }}">{{ __('site.nav.contact') }}</a>
            </div>
        </div>
    </section>
</main>
@include('public.partials.footer')
</body>
</html>
