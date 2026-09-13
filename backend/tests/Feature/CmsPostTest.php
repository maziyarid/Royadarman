<?php

namespace Tests\Feature;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Models\Cms\Post;
use App\Models\Cms\PostRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CmsPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_multilingual_post_with_revisions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->postJson('/api/v1/cms/posts', [
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'translations' => [
                ['locale' => 'fa', 'title' => 'راهنمای ایمپلنت', 'slug' => 'implant-guide-fa', 'body' => '<p>محتوای مقاله</p>'],
                ['locale' => 'en', 'title' => 'Implant Guide', 'slug' => 'implant-guide-en', 'body' => '<p>Article body</p>'],
            ],
        ])->assertCreated();

        $post = Post::first();
        $this->assertSame(2, $post->translations()->count());
        $this->assertSame(2, PostRevision::where('post_id', $post->id)->count());
    }

    public function test_patient_cannot_access_cms(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient)
            ->getJson('/api/v1/cms/posts')
            ->assertForbidden();

        $this->actingAs($patient)
            ->postJson('/api/v1/cms/posts', [
                'type' => PostType::Post->value,
                'status' => PostStatus::Draft->value,
                'translations' => [['locale' => 'fa', 'title' => 'x', 'slug' => 'x', 'body' => 'x']],
            ])
            ->assertForbidden();
    }

    public function test_clinician_cannot_access_cms(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);

        $this->actingAs($clinician)
            ->getJson('/api/v1/cms/posts')
            ->assertForbidden();
    }

    public function test_xss_is_sanitized_from_post_body(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->postJson('/api/v1/cms/posts', [
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'Test',
                    'slug' => 'xss-test',
                    'body' => '<p>safe</p><script>alert("xss")</script><a href="javascript:evil">link</a><img src="x" onerror="boom()">',
                ],
            ],
        ])->assertCreated();

        $post = Post::first();
        $sanitized = $post->translations()->first()->sanitized_body;

        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
        $this->assertStringContainsString('<p>safe</p>', $sanitized);
    }

    public function test_owner_can_publish_and_unpublish(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->postJson('/api/v1/cms/posts', [
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'translations' => [['locale' => 'fa', 'title' => 't', 'slug' => 's', 'body' => 'b']],
        ])->assertCreated();

        $post = Post::first();

        $this->actingAs($owner)
            ->postJson("/api/v1/cms/posts/{$post->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', PostStatus::Published->value);

        $this->actingAs($owner)
            ->postJson("/api/v1/cms/posts/{$post->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', PostStatus::Draft->value);
    }

    public function test_published_scope_only_returns_published_posts(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->postJson('/api/v1/cms/posts', [
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'translations' => [['locale' => 'fa', 'title' => 'draft', 'slug' => 'draft-slug', 'body' => 'b']],
        ])->assertCreated();

        $this->assertSame(0, Post::published()->count());

        $post = Post::first();
        $post->update(['status' => PostStatus::Published, 'published_at' => now()->subHour()]);

        $this->assertSame(1, Post::published()->count());
    }

    public function test_archived_post_cannot_be_updated(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->postJson('/api/v1/cms/posts', [
            'type' => PostType::Post->value,
            'status' => PostStatus::Published->value,
            'published_at' => now()->subDay()->toIso8601String(),
            'translations' => [['locale' => 'fa', 'title' => 't', 'slug' => 's', 'body' => 'b']],
        ])->assertCreated();

        $post = Post::first();
        $post->update(['status' => PostStatus::Archived]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/cms/posts/{$post->id}", ['is_featured' => true])
            ->assertForbidden();
    }

    public function test_coordinator_can_edit_but_not_delete(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);

        $this->actingAs($owner)->postJson('/api/v1/cms/posts', [
            'type' => PostType::Post->value,
            'status' => PostStatus::Draft->value,
            'translations' => [['locale' => 'fa', 'title' => 't', 'slug' => 's', 'body' => 'b']],
        ])->assertCreated();

        $post = Post::first();

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/cms/posts/{$post->id}", ['is_featured' => true])
            ->assertOk();

        $this->actingAs($coordinator)
            ->deleteJson("/api/v1/cms/posts/{$post->id}")
            ->assertForbidden();
    }
}
