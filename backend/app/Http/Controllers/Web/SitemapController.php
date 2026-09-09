<?php

namespace App\Http\Controllers\Web;

use App\Domain\CMS\Enums\PostType;
use App\Models\Cms\Post;
use Illuminate\Http\Response;

final class SitemapController
{
    public function index(): Response
    {
        $locales = ['fa', 'ar', 'en'];

        return response()->view('seo.sitemap-index', [
            'locales' => $locales,
        ], 200, ['Content-Type' => 'application/xml; charset=UTF-8'])
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function locale(string $locale): Response
    {
        if (! in_array($locale, ['fa', 'ar', 'en'], true)) {
            abort(404);
        }

        $home = [
            'url' => url('/'.$locale.'/'),
            'lastmod' => now()->toDateString(),
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ];

        $posts = Post::query()
            ->published()
            ->whereIn('type', [PostType::Post->value, PostType::Page->value, PostType::Service->value])
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->get();

        $urls = [$home];

        foreach ($posts as $post) {
            $translation = $post->translations->firstWhere('locale', $locale);
            if (! $translation || empty($translation->slug)) {
                continue;
            }

            $route = match ($post->type->value) {
                PostType::Page->value => '/'.$locale.'/'.$translation->slug,
                PostType::Service->value => '/'.$locale.'/services/'.$translation->slug,
                default => '/'.$locale.'/blog/'.$translation->slug,
            };

            $urls[] = [
                'url' => url($route),
                'lastmod' => ($post->updated_at ?? $post->published_at ?? now())->toDateString(),
                'changefreq' => $post->type === PostType::Page ? 'monthly' : 'weekly',
                'priority' => $post->type === PostType::Page ? '0.8' : '0.7',
            ];
        }

        return response()->view('seo.sitemap-locale', [
            'urls' => $urls,
        ], 200, ['Content-Type' => 'application/xml; charset=UTF-8'])
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
