<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($locales as $locale)
    <sitemap>
        <loc>{{ url('/sitemap-'.$locale.'.xml') }}</loc>
        <lastmod>{{ now()->toDateString() }}</lastmod>
    </sitemap>
@endforeach
</sitemapindex>
