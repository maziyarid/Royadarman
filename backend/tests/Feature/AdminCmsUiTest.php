<?php

namespace Tests\Feature;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Models\Cms\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCmsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_cms(): void
    {
        $this->get('/admin/cms/posts')->assertRedirect('/fa/');
    }

    public function test_patient_is_forbidden_from_admin_cms(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $this->actingAs($patient)->get('/admin/cms/posts')->assertForbidden();
    }

    public function test_owner_can_list_posts_index(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $response = $this->actingAs($owner)->get('/admin/cms/posts')
            ->assertOk()
            ->assertSee(__('ui.admin.posts'), false)
            ->assertSee(__('ui.admin.new_post'), false);
    }

    public function test_owner_can_create_and_store_a_multilingual_post(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/cms/posts/create')->assertOk()->assertSee(__('ui.admin.save'), false);

        $this->actingAs($owner)->post('/admin/cms/posts', [
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'is_featured' => false,
            'translations' => [
                ['locale' => 'fa', 'title' => 'راهنمای ایمپلنت', 'slug' => 'implant-fa', 'body' => '<p>متن</p>'],
                ['locale' => 'en', 'title' => 'Implant Guide', 'slug' => 'implant-en', 'body' => '<p>Body</p>'],
            ],
        ])->assertRedirect();

        $post = Post::first();
        $this->assertNotNull($post);
        $this->assertSame(2, $post->translations()->count());
    }

    public function test_post_body_is_sanitized_against_stored_xss(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $payload = '<p>ok</p><script>alert(1)</script><img src="javascript:alert(1)" onerror="alert(2)"><a href="javascript:alert(3)">x</a><!--[if IE]><script>alert(4)</script><![endif]-->';

        $this->actingAs($owner)->post('/admin/cms/posts', [
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'is_featured' => false,
            'translations' => [
                ['locale' => 'fa', 'title' => 'XSS test', 'slug' => 'xss-test', 'body' => $payload],
            ],
        ])->assertRedirect();

        $body = Post::first()->translations()->first()->sanitized_body;
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('<!--[if', $body);
        $this->assertStringContainsString('<p>ok</p>', $body);
    }

    public function test_owner_can_publish_a_post_from_admin(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $post = Post::query()->create([
            'author_user_id' => $owner->id,
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'published_at' => null,
        ]);
        $post->translations()->create(['locale' => 'fa', 'title' => 'پیش‌نویس', 'slug' => 'draft-1', 'body' => '<p>متن</p>', 'sanitized_body' => '<p>متن</p>']);

        $this->actingAs($owner)->post("/admin/cms/posts/{$post->id}/publish")
            ->assertRedirect();

        $this->assertSame(PostStatus::Published->value, $post->fresh()->status->value);
    }
}
