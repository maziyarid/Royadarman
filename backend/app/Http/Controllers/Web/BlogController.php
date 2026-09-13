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

    public function indexPersian(): Response
    {
        app()->setLocale('fa');

        return $this->index('fa');
    }

    public function index(string $locale): Response
    {
        $translations = PostTranslation::query()
            ->where('locale', $locale)
            ->whereHas('post', fn ($query) => $query
                ->where('type', PostType::Post->value)
                ->where('status', PostStatus::Published->value)
                ->where('published_at', '<=', now()))
            ->with(['post.author'])
            ->orderByDesc('post_id')
            ->paginate(12);

        return response()->view('public.blog.index', [
            'locale' => $locale,
            'translations' => $translations,
            'canonical' => $this->blogUrl($locale),
            'alternates' => collect(['fa', 'ar', 'en'])->mapWithKeys(fn (string $lang) => [$lang => $this->blogUrl($lang)])->all(),
        ], 200)->header('Cache-Control', 'public, max-age=300');
    }

    public function showPersian(string $slug): Response
    {
        app()->setLocale('fa');

        return $this->show('fa', $slug);
    }

    public function show(string $locale, string $slug): Response
    {
        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->firstOrFail();

        $post = $translation->post()->with(['translations', 'author'])->firstOrFail();
        abort_unless($post->type === PostType::Post, 404);
        abort_unless($post->status === PostStatus::Published && $post->published_at?->isPast(), 404);

        $seo = $post->seoMetadata()->where('locale', $locale)->first();
        $alternates = $this->alternates($post);
        $canonical = $seo?->canonical_url ?: $this->blogUrl($locale, $slug);
        $robots = $seo?->robots_directive ?: 'index, follow';
        $ogImage = $seo?->ogImage ? route('cms.media.serve', $seo->ogImage) : null;
        $schema = $seo?->schema_data ?: $this->structuredData->article($post, $translation, $locale, $canonical);

        return response()->view('public.blog.show', compact(
            'post', 'translation', 'locale', 'seo', 'canonical', 'robots', 'alternates', 'ogImage', 'schema'
        ), 200)->header('Cache-Control', 'public, max-age=300');
    }

    /** Authenticated, signed-URL preview of any post state, always noindex. */
    public function preview(string $locale, string $slug): Response
    {
        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->firstOrFail();
        $post = $translation->post()->with(['translations', 'author'])->firstOrFail();
        abort_unless($post->type === PostType::Post, 404);
        abort_unless($this->canView($post), 403);

        $seo = $post->seoMetadata()->where('locale', $locale)->first();
        $alternates = $this->alternates($post);
        $canonical = $this->blogUrl($locale, $slug);
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

    /** @return array<string,string> */
    private function alternates(Post $post): array
    {
        $alternates = [];
        foreach (['fa', 'ar', 'en'] as $lang) {
            $translation = $post->translations->firstWhere('locale', $lang);
            if ($translation?->slug) {
                $alternates[$lang] = $this->blogUrl($lang, $translation->slug);
            }
        }

        return $alternates;
    }

    private function blogUrl(string $locale, ?string $slug = null): string
    {
        $prefix = $locale === 'fa' ? '' : '/'.$locale;
        $path = $prefix.'/blog'.($slug ? '/'.$slug : '');

        return url($path ?: '/blog');
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
