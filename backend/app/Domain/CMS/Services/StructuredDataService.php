<?php

namespace App\Domain\CMS\Services;

use App\Models\Cms\Post;
use App\Models\Cms\PostTranslation;

final class StructuredDataService
{
    /**
     * Build an Article schema.org JSON-LD array for a published CMS post.
     * Uses only attributes supported by actual visible content (§27).
     *
     * @return array<string, mixed>
     */
    public function article(Post $post, PostTranslation $translation, string $locale, string $canonical): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $translation->title,
            'url' => $canonical,
            'inLanguage' => $locale,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonical,
            ],
        ];

        $schema['author'] = [
            '@type' => 'Organization',
            'name' => 'Royadarman',
        ];

        if ($translation->excerpt) {
            $schema['description'] = $translation->excerpt;
        }

        return $schema;
    }

    /**
     * Build an Organization schema for the platform home/public site.
     * Does NOT represent the business owner as a dentist (§27).
     *
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Royadarman',
            'url' => url('/fa/'),
        ];
    }

    /**
     * Build a WebPage schema for a published CMS page.
     * Does NOT fabricate medical/provider claims (§27).
     *
     * @return array<string, mixed>
     */
    public function webPage(Post $post, PostTranslation $translation, string $locale, string $canonical): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $translation->title,
            'url' => $canonical,
            'inLanguage' => $locale,
            'dateModified' => $post->updated_at?->toIso8601String(),
        ];
        if ($translation->excerpt) {
            $schema['description'] = $translation->excerpt;
        }

        return $schema;
    }

    /**
     * Build a Service schema for a published CMS service page.
     * Describes coordination only — no fabricated provider/pricing/availability (§27).
     *
     * @return array<string, mixed>
     */
    public function service(Post $post, PostTranslation $translation, string $locale, string $canonical): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $translation->title,
            'url' => $canonical,
            'inLanguage' => $locale,
            'provider' => [
                '@type' => 'Organization',
                'name' => 'Royadarman',
                'url' => url('/fa/'),
            ],
        ];
        if ($translation->excerpt) {
            $schema['description'] = $translation->excerpt;
        }

        return $schema;
    }
}
