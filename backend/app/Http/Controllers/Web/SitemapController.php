<?php

namespace App\Http\Controllers\Web;

use App\Domain\CMS\Enums\PostType;
use App\Models\Cms\Post;
use Illuminate\Http\Response;

final class SitemapController
{
    private const STATIC_ROUTE_NAMES = [
        'public.home',
        'public.services',
        'public.opg',
        'public.home-dentistry',
        'public.referrals',
        'public.how',
        'public.about',
        'public.contact',
        'public.privacy',
        'public.faq',
        'public.blog.index',
    ];

    public function index(): Response
    {
        return response()->view('seo.sitemap-index', ['locales' => ['fa', 'ar', 'en']], 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function locale(string $locale): Response
    {
        abort_unless(in_array($locale, ['fa', 'ar', 'en'], true), 404);

        $urls = [];
        foreach (self::STATIC_ROUTE_NAMES as $name) {
            $url = $locale === 'fa'
                ? route($name.'.fa')
                : route($name, ['locale' => $locale]);
            $urls[$url] = [
                'url' => $url,
                'lastmod' => now()->toDateString(),
                'changefreq' => $name === 'public.home' ? 'weekly' : 'monthly',
                'priority' => $name === 'public.home' ? '1.0' : '0.8',
            ];
        }

        $posts = Post::query()
            ->published()
            ->whereIn('type', [PostType::Post->value, PostType::Page->value, PostType::Service->value])
            ->whereHas('translations', fn ($query) => $query->where('locale', $locale))
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->get();

        foreach ($posts as $post) {
            $translation = $post->translations->firstWhere('locale', $locale);
            if (! $translation?->slug) {
                continue;
            }

            $prefix = $locale === 'fa' ? '' : '/'.$locale;
            $path = match ($post->type) {
                PostType::Page => $prefix.'/'.$translation->slug,
                PostType::Service => $prefix.'/services/'.$translation->slug,
                default => $prefix.'/blog/'.$translation->slug,
            };
            $url = url($path);
            $urls[$url] = [
                'url' => $url,
                'lastmod' => ($post->updated_at ?? $post->published_at ?? now())->toDateString(),
                'changefreq' => $post->type === PostType::Page ? 'monthly' : 'weekly',
                'priority' => $post->type === PostType::Page ? '0.8' : '0.7',
            ];
        }

        return response()->view('seo.sitemap-locale', ['urls' => array_values($urls)], 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
