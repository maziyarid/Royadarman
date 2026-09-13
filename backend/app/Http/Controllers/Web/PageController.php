<?php

namespace App\Http\Controllers\Web;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Domain\CMS\Services\StructuredDataService;
use App\Models\Cms\Post;
use App\Models\Cms\PostTranslation;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class PageController
{
    public function __construct(private readonly StructuredDataService $structuredData) {}

    public function showPersianPage(string $slug): SymfonyResponse
    {
        app()->setLocale('fa');

        return $this->render('fa', $slug, PostType::Page);
    }

    public function showPersianService(string $slug): SymfonyResponse
    {
        app()->setLocale('fa');

        return $this->render('fa', $slug, PostType::Service);
    }

    public function showPage(string $locale, string $slug): SymfonyResponse
    {
        return $this->render($locale, $slug, PostType::Page);
    }

    public function showService(string $locale, string $slug): SymfonyResponse
    {
        return $this->render($locale, $slug, PostType::Service);
    }

    private function render(string $locale, string $slug, PostType $type): SymfonyResponse
    {
        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->first();

        if (! $translation) {
            return app(PublicRedirectController::class)->resolve(request());
        }

        $post = $translation->post()->with(['translations', 'author'])->firstOrFail();

        abort_unless($post->status === PostStatus::Published && $post->published_at?->isPast(), 404);
        abort_unless($post->type === $type, 404);

        $seo = $post->seoMetadata()->where('locale', $locale)->first();
        $alternates = $this->alternates($post, $type);
        $canonical = $seo?->canonical_url ?: $this->publicUrl($locale, $slug, $type);
        $robots = $seo?->robots_directive ?: 'index, follow';
        $ogImage = $seo?->ogImage ? route('cms.media.serve', $seo->ogImage) : null;
        $schema = match ($type) {
            PostType::Service => $this->structuredData->service($post, $translation, $locale, $canonical),
            default => $this->structuredData->webPage($post, $translation, $locale, $canonical),
        };

        return response()->view('public.page.show', compact(
            'post', 'translation', 'locale', 'seo', 'canonical', 'robots', 'alternates', 'ogImage', 'schema'
        ), 200)->header('Cache-Control', 'public, max-age=300');
    }

    /** @return array<string,string> */
    private function alternates(Post $post, PostType $type): array
    {
        $alternates = [];
        foreach (['fa', 'ar', 'en'] as $lang) {
            $translation = $post->translations->firstWhere('locale', $lang);
            if ($translation?->slug) {
                $alternates[$lang] = $this->publicUrl($lang, $translation->slug, $type);
            }
        }

        return $alternates;
    }

    private function publicUrl(string $locale, string $slug, PostType $type): string
    {
        $prefix = $locale === 'fa' ? '' : '/'.$locale;
        $segment = $type === PostType::Service ? '/services/' : '/';

        return url($prefix.$segment.$slug);
    }
}
