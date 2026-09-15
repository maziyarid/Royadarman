<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PublicUrl;
use Database\Seeders\CmsStarterContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicStoryAndErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_story_pages_include_photography_and_safety_copy(): void
    {
        $this->get('/services/opg')
            ->assertOk()
            ->assertSee('/assets/photos/opg.webp', false)
            ->assertSee('width="1600"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertDontSee('TEST workflow placeholder');

        $this->get('/en/services/home-dentistry')
            ->assertOk()
            ->assertSee('/assets/photos/home.webp', false)
            ->assertSee('Tehran', false)
            ->assertDontSee('lorem ipsum', false);

        $this->get('/ar/referrals')
            ->assertOk()
            ->assertSee('/assets/photos/coord.webp', false)
            ->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_locale_switcher_keeps_the_equivalent_public_page(): void
    {
        $html = $this->get('/en/services/opg')->assertOk()->getContent();
        $this->assertStringContainsString('href="http://localhost/services/opg"', $html);
        $this->assertStringContainsString('href="http://localhost/ar/services/opg"', $html);
        $this->assertStringContainsString('href="http://localhost/en/services/opg"', $html);
    }

    public function test_public_url_helper_preserves_locale_specific_routes(): void
    {
        $this->assertSame(url('/'), PublicUrl::to('home', 'fa'));
        $this->assertSame(url('/en'), PublicUrl::to('home', 'en'));
        $this->assertSame(url('/services/opg'), PublicUrl::to('opg', 'fa'));
        $this->assertSame(url('/ar/services/home-dentistry'), PublicUrl::to('home-dentistry', 'ar'));
        $this->assertSame(url('/en/privacy'), PublicUrl::to('privacy', 'en'));
    }

    public function test_html_error_pages_are_localised_and_do_not_leak_traces(): void
    {
        $notFound = $this->get('/this-page-does-not-exist-royadarman');
        $notFound->assertNotFound()
            ->assertSee(__('ui.errors.404_title'), false)
            ->assertDontSee('stack trace', false)
            ->assertDontSee('Symfony\\Component', false);

        $this->get('/en/this-page-does-not-exist-royadarman')
            ->assertNotFound()
            ->assertSee('Page not found');
    }

    public function test_cms_starter_articles_are_organisational_and_trilingual(): void
    {
        User::factory()->create(['role' => 'owner']);
        $this->seed(CmsStarterContentSeeder::class);

        $this->get('/blog/royadarman-chist')
            ->assertOk()
            ->assertSee('رویا درمان چیست')
            ->assertSee(__('ui.blog_org_author'), false)
            ->assertDontSee('Dr.', false);

        $this->get('/en/blog/what-royadarman-is')
            ->assertOk()
            ->assertSee('What Royadarman is')
            ->assertSee('"@type":"Article"', false);
    }
}
