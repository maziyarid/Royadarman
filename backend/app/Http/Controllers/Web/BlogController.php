<?php

namespace App\Http\Controllers\Web;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Services\StructuredDataService;
use App\Models\Cms\PostTranslation;
use Illuminate\Http\Response;

final class BlogController
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

        $seo = $post->seoMetadata()->where('locale', $locale)->first();

        $alternates = [];
        foreach (['fa', 'ar', 'en'] as $lang) {
            $altTranslation = $post->translations->firstWhere('locale', $lang);
            if ($altTranslation && $altTranslation->slug) {
                $alternates[$lang] = url('/'.$lang.'/blog/'.$altTranslation->slug);
            }
        }

        $canonical = $seo?->canonical_url ?: url('/'.$locale.'/blog/'.$slug);
        $robots = $seo?->robots_directive ?: 'index, follow';
        $ogImage = $seo?->ogImage ? route('cms.media.serve', $seo->ogImage) : null;

        $schema = $seo?->schema_data ?: $this->structuredData->article($post, $translation, $locale, $canonical);

        return response()->view('public.blog.show', [
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
