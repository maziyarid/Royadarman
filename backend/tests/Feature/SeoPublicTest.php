<?php

namespace Tests\Feature;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Models\Cms\Media;
use App\Models\Cms\Post;
use App\Models\Cms\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class SeoPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_organization_structured_data(): void
    {
        $this->get('/fa/')
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"name":"Royadarman"', false);
    }

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
            ->assertSee('راهنمای ایمپلنت', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Article"', false)
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

    public function test_preview_requires_valid_signed_url_and_auth(): void
    {
        $author = User::factory()->create(['role' => 'owner']);
        $post = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'published_at' => null,
        ]);
        $post->translations()->create(['locale' => 'fa', 'title' => 'پیش‌نویس', 'slug' => 'draft-preview', 'body' => '<p>متن</p>', 'sanitized_body' => '<p>متن</p>']);

        // Unsigned URL → 403 (signed middleware)
        $this->actingAs($author)->get('/fa/blog/draft-preview/preview')->assertForbidden();

        // Signed URL as staff → 200 with noindex and preview banner
        $signed = URL::signedRoute('public.blog.preview', ['locale' => 'fa', 'slug' => 'draft-preview'], now()->addMinutes(15));
        $this->actingAs($author)->get($signed)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('preview-banner', false)
            ->assertSee('noindex, nofollow', false)
            ->assertSee('پیش‌نویس', false);
    }

    public function test_preview_signed_url_rejects_patient(): void
    {
        $author = User::factory()->create(['role' => 'owner']);
        $patient = User::factory()->create(['role' => 'patient']);
        $post = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'published_at' => null,
        ]);
        $post->translations()->create(['locale' => 'fa', 'title' => 'پیش‌نویس', 'slug' => 'draft-preview-2', 'body' => '<p>متن</p>', 'sanitized_body' => '<p>متن</p>']);

        $signed = URL::signedRoute('public.blog.preview', ['locale' => 'fa', 'slug' => 'draft-preview-2'], now()->addMinutes(15));
        $this->actingAs($patient)->get($signed)->assertForbidden();
    }

    public function test_redirect_manager_issues_301_for_known_source_path(): void
    {
        Redirect::create([
            'source_path' => '/old-page',
            'destination_url' => '/fa/',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/old-page')->assertRedirect('/fa/')->assertStatus(301);
        $this->assertSame(1, Redirect::where('source_path', '/old-page')->first()->hit_count);
    }

    public function test_redirect_manager_issues_302_when_configured(): void
    {
        Redirect::create([
            'source_path' => '/temp-page',
            'destination_url' => '/fa/services',
            'status_code' => 302,
            'is_active' => true,
        ]);

        $this->get('/temp-page')->assertRedirect('/fa/services')->assertStatus(302);
    }

    public function test_inactive_redirect_returns_404(): void
    {
        Redirect::create([
            'source_path' => '/disabled',
            'destination_url' => '/fa/',
            'status_code' => 301,
            'is_active' => false,
        ]);

        $this->get('/disabled')->assertNotFound();
    }

    public function test_unknown_path_returns_404_not_redirect(): void
    {
        $this->get('/this-does-not-exist-anywhere')->assertNotFound();
    }

    public function test_published_cms_page_renders_with_canonical_and_schema(): void
    {
        $author = User::factory()->create();
        $post = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Page->value,
            'status' => PostStatus::Published->value,
            'published_at' => now(),
        ]);
        $post->translations()->create(['locale' => 'fa', 'title' => 'درباره ما', 'slug' => 'about-us', 'body' => '<p>صفحه درباره ما</p>', 'sanitized_body' => '<p>صفحه درباره ما</p>']);
        $post->translations()->create(['locale' => 'en', 'title' => 'About Us', 'slug' => 'about-us-en', 'body' => '<p>About page</p>', 'sanitized_body' => '<p>About page</p>']);

        $this->get('/fa/about-us')
            ->assertOk()
            ->assertSee('<html lang="fa" dir="rtl">', false)
            ->assertSee('<link rel="canonical" href="'.url('/fa/about-us').'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/about-us-en').'">', false)
            ->assertSee('درباره ما', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"WebPage"', false);
    }

    public function test_published_service_page_renders_with_service_schema(): void
    {
        $author = User::factory()->create();
        $post = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Service->value,
            'status' => PostStatus::Published->value,
            'published_at' => now(),
        ]);
        $post->translations()->create(['locale' => 'fa', 'title' => 'خدمات دندانپزشکی در منزل', 'slug' => 'home-dentistry', 'body' => '<p>توضیحات</p>', 'sanitized_body' => '<p>توضیحات</p>']);

        $this->get('/fa/services/home-dentistry')
            ->assertOk()
            ->assertSee('خدمات دندانپزشکی در منزل', false)
            ->assertSee('"@type":"Service"', false)
            ->assertSee('"name":"Royadarman"', false);
    }

    public function test_draft_page_returns_404(): void
    {
        $author = User::factory()->create();
        $post = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Page->value,
            'status' => PostStatus::Draft->value,
            'published_at' => null,
        ]);
        $post->translations()->create(['locale' => 'fa', 'title' => 'پیش‌نویس', 'slug' => 'draft-page', 'body' => '<p></p>', 'sanitized_body' => '<p></p>']);

        $this->get('/fa/draft-page')->assertNotFound();
    }

    public function test_blog_post_is_not_accessible_via_page_route(): void
    {
        $author = User::factory()->create();
        $post = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Post->value,
            'status' => PostStatus::Published->value,
            'published_at' => now(),
        ]);
        $post->translations()->create(['locale' => 'fa', 'title' => 'مقاله', 'slug' => 'blog-post-via-page', 'body' => '<p></p>', 'sanitized_body' => '<p></p>']);

        // A blog post type must NOT render via the page route (only blog/{slug}).
        $this->get('/fa/blog-post-via-page')->assertNotFound();
    }

    public function test_cms_media_is_served_publicly(): void
    {
        \Storage::fake('public-cms');
        $file = File::image('og.png', 200, 200);
        $storageKey = $file->store('media', 'public-cms');

        $media = Media::create([
            'uploaded_by_user_id' => User::factory()->create()->id,
            'disk' => 'public-cms',
            'storage_key' => $storageKey,
            'original_filename' => 'og.png',
            'mime_type' => 'image/png',
            'byte_size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'width' => 200,
            'height' => 200,
        ]);

        $this->get(route('cms.media.serve', $media))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_non_image_cms_media_returns_404(): void
    {
        \Storage::fake('public-cms');
        $media = Media::create([
            'uploaded_by_user_id' => User::factory()->create()->id,
            'disk' => 'public-cms',
            'storage_key' => 'media/doc.txt',
            'original_filename' => 'doc.txt',
            'mime_type' => 'text/plain',
            'byte_size' => 10,
            'sha256' => 'abc',
        ]);

        $this->get(route('cms.media.serve', $media))->assertNotFound();
    }

    public function test_home_and_page_render_correct_brand_name(): void
    {
        $this->get('/fa/')
            ->assertOk()
            ->assertSee('رویا درمان', false)
            ->assertDontSee('رویاد', false);

        $author = User::factory()->create();
        $post = Post::query()->create([
            'author_user_id' => $author->id,
            'type' => PostType::Page->value,
            'status' => PostStatus::Published->value,
            'published_at' => now(),
        ]);
        $post->translations()->create([
            'locale' => 'fa',
            'title' => 'درباره ما',
            'slug' => 'about-us',
            'body' => '<p>محتوای صفحه</p>',
            'sanitized_body' => '<p>محتوای صفحه</p>',
        ]);

        $this->get('/fa/about-us')
            ->assertOk()
            ->assertSee('رویا درمان', false)
            ->assertDontSee('رویاد', false);
    }
}
