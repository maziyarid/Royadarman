<?php

namespace Tests\Feature;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Models\Cms\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SeoPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_is_served_with_sitemap_reference(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee('Disallow: /api/')
            ->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    public function test_sitemap_index_lists_per_locale_sitemaps(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<sitemapindex', false)
            ->assertSee(url('/sitemap-fa.xml'), false)
            ->assertSee(url('/sitemap-ar.xml'), false)
            ->assertSee(url('/sitemap-en.xml'), false);
    }

    public function test_locale_sitemap_includes_home_and_published_posts_only(): void
    {
        $author = User::factory()->create();
        $published = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Post->value,
            'status' => PostStatus::Published->value,
            'published_at' => now(),
        ]);
        $published->translations()->create(['locale' => 'fa', 'title' => 'راهنمای ایمپلنت', 'slug' => 'implant-guide', 'body' => '<p>متن</p>', 'sanitized_body' => '<p>متن</p>']);

        $draft = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'published_at' => null,
        ]);
        $draft->translations()->create(['locale' => 'fa', 'title' => 'پیش‌نویس', 'slug' => 'draft-slug', 'body' => '<p>متن</p>', 'sanitized_body' => '<p>متن</p>']);

        $response = $this->get('/sitemap-fa.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $response->assertSee(url('/fa/'), false)
            ->assertSee(url('/fa/blog/implant-guide'), false)
            ->assertDontSee(url('/fa/blog/draft-slug'), false);
    }

    public function test_blog_show_renders_published_post_with_canonical_and_hreflang(): void
    {
        $author = User::factory()->create();
        $post = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Post->value,
            'status' => PostStatus::Published->value,
            'published_at' => now(),
        ]);
        $post->translations()->create(['locale' => 'fa', 'title' => 'راهنمای ایمپلنت', 'slug' => 'implant-guide', 'body' => '<p>متن مقاله</p>', 'sanitized_body' => '<p>متن مقاله</p>']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Implant Guide', 'slug' => 'implant-guide-en', 'body' => '<p>Article body</p>', 'sanitized_body' => '<p>Article body</p>']);

        $response = $this->get('/fa/blog/implant-guide')
            ->assertOk()
            ->assertSee('<html lang="fa" dir="rtl">', false)
            ->assertSee('<link rel="canonical" href="'.url('/fa/blog/implant-guide').'">', false)
            ->assertSee('<link rel="alternate" hreflang="fa" href="'.url('/fa/blog/implant-guide').'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/blog/implant-guide-en').'">', false)
            ->assertSee('راهنمای ایمپلنت', false);
    }

    public function test_blog_show_returns_404_for_draft_post(): void
    {
        $author = User::factory()->create();
        $post = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'published_at' => null,
        ]);
        $post->translations()->create(['locale' => 'fa', 'title' => 'پیش‌نویس', 'slug' => 'not-published', 'body' => '<p>متن</p>', 'sanitized_body' => '<p>متن</p>']);

        $this->get('/fa/blog/not-published')->assertNotFound();
    }
}
