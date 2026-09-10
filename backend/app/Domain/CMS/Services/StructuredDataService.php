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

        $author = $post->author;
        if ($author) {
            $schema['author'] = [
                '@type' => 'Person',
                'name' => $author->name,
            ];
        }

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
}
