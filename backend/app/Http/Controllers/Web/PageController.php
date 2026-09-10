<?php

namespace App\Http\Controllers\Web;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Domain\CMS\Services\StructuredDataService;
use App\Models\Cms\PostTranslation;
use Illuminate\Http\Response;

final class PageController
{
    public function __construct(private readonly StructuredDataService $structuredData) {}

    public function show(string $locale, string $slug): Response
    {
        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->firstOrFail();

        $post = $translation->post()->with(['translations', 'author'])->firstOrFail();

        abort_unless($post->status === PostStatus::Published && $post->published_at?->isPast(), 404);
        abort_unless(in_array($post->type, [PostType::Page, PostType::Service], true), 404);

        $seo = $post->seoMetadata()->where('locale', $locale)->first();

        $alternates = [];
        foreach (['fa', 'ar', 'en'] as $lang) {
            $altTranslation = $post->translations->firstWhere('locale', $lang);
            if ($altTranslation && $altTranslation->slug) {
                $alternates[$lang] = url('/'.$lang.'/'.$altTranslation->slug);
            }
        }
        $canonical = $seo?->canonical_url ?: url('/'.$locale.'/'.$slug);
        $robots = $seo?->robots_directive ?: 'index, follow';
        $ogImage = $seo?->ogImage ? route('cms.media.serve', $seo->ogImage) : null;

        $schema = match ($post->type) {
            PostType::Service => $this->structuredData->service($post, $translation, $locale, $canonical),
            default => $this->structuredData->webPage($post, $translation, $locale, $canonical),
        };

        return response()->view('public.page.show', [
            'post' => $post,
            'translation' => $translation,
            'locale' => $locale,
            'seo' => $seo,
            'canonical' => $canonical,
            'robots' => $robots,
            'alternates' => $alternates,
            'ogImage' => $ogImage,
            'schema' => $schema,
        ], 200)
            ->header('Cache-Control', 'public, max-age=300');
    }
}
