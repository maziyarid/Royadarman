@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($urls as $u)
    <url>
        <loc>{{ $u['url'] }}</loc>
        <lastmod>{{ $u['lastmod'] }}</lastmod>
        <changefreq>{{ $u['changefreq'] }}</changefreq>
        <priority>{{ $u['priority'] }}</priority>
    </url>
@endforeach
</urlset>
