<?php

namespace App\Http\Controllers\Web;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Domain\CMS\Services\StructuredDataService;
use App\Models\Cms\Post;
use App\Models\Cms\PostTranslation;
use Illuminate\Http\Response;

final class BlogController
{
    public function __construct(private readonly StructuredDataService $structuredData) {}

    public function index(string $locale): Response
    {
        $translations = PostTranslation::query()
            ->where('locale', $locale)
            ->whereHas('post', fn ($q) => $q
                ->where('type', PostType::Post)
                ->where('status', PostStatus::Published)
                ->where('published_at', '<=', now()))
            ->with(['post.author'])
            ->orderByDesc('post_id')
            ->paginate(12);

        return response()->view('public.blog.index', [
            'locale' => $locale,
            'translations' => $translations,
            'canonical' => url('/'.$locale.'/blog/'),
        ], 200)
            ->header('Cache-Control', 'public, max-age=300');
    }

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

    /**
     * Authenticated, signed-URL preview of any post state (incl. drafts). noindex.
     */
    public function preview(string $locale, string $slug): Response
    {
        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->firstOrFail();

        $post = $translation->post()->with(['translations', 'author'])->firstOrFail();

        if (! $this->canView($post)) {
            abort(403);
        }

        $seo = $post->seoMetadata()->where('locale', $locale)->first();
        $alternates = [];
        foreach (['fa', 'ar', 'en'] as $lang) {
            $altTranslation = $post->translations->firstWhere('locale', $lang);
            if ($altTranslation && $altTranslation->slug) {
                $alternates[$lang] = url('/'.$lang.'/blog/'.$altTranslation->slug);
            }
        }
        $canonical = url('/'.$locale.'/blog/'.$slug);
        $ogImage = $seo?->ogImage ? route('cms.media.serve', $seo->ogImage) : null;
        $schema = $this->structuredData->article($post, $translation, $locale, $canonical);

        return response()->view('public.blog.show', [
            'post' => $post,
            'translation' => $translation,
            'locale' => $locale,
            'seo' => $seo,
            'canonical' => $canonical,
            'robots' => 'noindex, nofollow',
            'alternates' => $alternates,
            'ogImage' => $ogImage,
            'schema' => $schema,
            'isPreview' => true,
        ], 200)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function canView(Post $post): bool
    {
        $user = request()->user();
        if (! $user) {
            return false;
        }

        if ($post->status === PostStatus::Published && $post->published_at?->isPast()) {
            return true;
        }

        return $user->can('view', $post);
    }
}
