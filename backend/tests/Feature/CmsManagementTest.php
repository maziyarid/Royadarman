<?php

namespace Tests\Feature;

use App\Domain\CMS\Enums\CommentStatus;
use App\Models\Cms\Comment;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\MenuTranslation;
use App\Models\Cms\Post;
use App\Models\Cms\SeoMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CmsManagementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function postOwner(User $owner): Post
    {
        return Post::query()->create([
            'author_user_id' => $owner->id,
            'type' => 'post',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    // --- Menus ----------------------------------------------------------------

    public function test_menu_create_with_items_and_translations(): void
    {
        $owner = $this->owner();

        $menu = $this->actingAs($owner)->postJson('/api/v1/cms/menus', [
            'location' => 'main',
            'slug' => 'main-nav',
            'translations' => [
                ['locale' => 'fa', 'title' => 'منوی اصلی'],
                ['locale' => 'en', 'title' => 'Main Menu'],
            ],
        ])->assertCreated();

        $menuId = $menu->json('data.id');

        $this->actingAs($owner)->postJson("/api/v1/cms/menus/{$menuId}/items", [
            'item_type' => 'url',
            'url' => '/about',
            'sort_order' => 1,
            'translations' => [
                ['locale' => 'fa', 'label' => 'درباره ما'],
                ['locale' => 'en', 'label' => 'About'],
            ],
        ])->assertCreated();

        $this->assertSame(1, Menu::query()->find($menuId)->items()->count());
        $this->assertSame(2, Menu::query()->find($menuId)->items()->first()->translations()->count());
    }

    public function test_menu_duplicate_slug_rejected(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->postJson('/api/v1/cms/menus', [
            'location' => 'main', 'slug' => 'dup', 'translations' => [['locale' => 'fa', 'title' => 'x']],
        ])->assertCreated();

        $this->actingAs($owner)->postJson('/api/v1/cms/menus', [
            'location' => 'main', 'slug' => 'dup', 'translations' => [['locale' => 'fa', 'title' => 'y']],
        ])->assertStatus(422);
    }

    public function test_menu_item_self_parent_prevented(): void
    {
        $owner = $this->owner();
        $menu = Menu::query()->create(['location' => 'main', 'slug' => 'selfpar']);
        MenuTranslation::query()->create(['menu_id' => $menu->id, 'locale' => 'fa', 'title' => 'x']);

        $item = $this->actingAs($owner)->postJson("/api/v1/cms/menus/{$menu->id}/items", [
            'item_type' => 'url', 'url' => '/a',
            'translations' => [['locale' => 'fa', 'label' => 'a']],
        ])->assertCreated()->json('data.id');

        $this->actingAs($owner)->patchJson("/api/v1/cms/menus/{$menu->id}/items/{$item}", [
            'parent_id' => $item,
        ]);

        $this->assertNull(MenuItem::query()->find($item)->parent_id);
    }

    public function test_patient_cannot_access_menus(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $this->actingAs($patient)->getJson('/api/v1/cms/menus')->assertForbidden();
    }

    // --- SEO ------------------------------------------------------------------

    public function test_seo_upsert_is_idempotent_per_entity_locale(): void
    {
        $owner = $this->owner();
        $post = $this->postOwner($owner);

        $payload = [
            'entity_type' => 'cms_posts',
            'entity_id' => $post->id,
            'locale' => 'fa',
            'seo_title' => 'عنوان سئو',
            'meta_description' => 'توضیح متا',
            'robots_directive' => 'index, follow',
        ];

        $this->actingAs($owner)->postJson('/api/v1/cms/seo', $payload)->assertOk();
        $this->actingAs($owner)->postJson('/api/v1/cms/seo', array_merge($payload, ['seo_title' => 'عنوان جدید']))->assertOk();

        $this->assertSame(1, SeoMetadata::query()->where('entity_id', $post->id)->count());
        $this->assertSame('عنوان جدید', SeoMetadata::query()->where('entity_id', $post->id)->first()->seo_title);
    }

    public function test_seo_locale_isolation(): void
    {
        $owner = $this->owner();
        $post = $this->postOwner($owner);

        $this->actingAs($owner)->postJson('/api/v1/cms/seo', [
            'entity_type' => 'cms_posts', 'entity_id' => $post->id, 'locale' => 'fa',
            'seo_title' => 'فارسی',
        ])->assertOk();

        $this->actingAs($owner)->postJson('/api/v1/cms/seo', [
            'entity_type' => 'cms_posts', 'entity_id' => $post->id, 'locale' => 'en',
            'seo_title' => 'English',
        ])->assertOk();

        $this->assertSame(2, SeoMetadata::query()->where('entity_id', $post->id)->count());
    }

    public function test_clinician_cannot_manage_seo(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->actingAs($clinician)
            ->postJson('/api/v1/cms/seo', [
                'entity_type' => 'cms_posts', 'entity_id' => 1, 'locale' => 'fa',
            ])
            ->assertForbidden();
    }

    // --- Comments -------------------------------------------------------------

    public function test_comment_moderation_changes_status(): void
    {
        $owner = $this->owner();
        $post = $this->postOwner($owner);

        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'author_name' => 'Visitor',
            'body' => 'Great article',
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/cms/comments/{$comment->id}", ['status' => 'approved'])
            ->assertOk();

        $this->assertSame(CommentStatus::Approved, $comment->refresh()->status);
    }

    public function test_comment_invalid_status_rejected(): void
    {
        $owner = $this->owner();
        $post = $this->postOwner($owner);
        $comment = Comment::query()->create([
            'post_id' => $post->id, 'author_name' => 'V', 'body' => 'b', 'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/cms/comments/{$comment->id}", ['status' => 'live'])
            ->assertStatus(422);
    }

    public function test_coordinator_can_moderate_but_not_delete_comment(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $owner = $this->owner();
        $post = $this->postOwner($owner);
        $comment = Comment::query()->create([
            'post_id' => $post->id, 'author_name' => 'V', 'body' => 'b', 'status' => 'pending',
        ]);

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/cms/comments/{$comment->id}", ['status' => 'spam'])
            ->assertOk();

        $this->actingAs($coordinator)
            ->deleteJson("/api/v1/cms/comments/{$comment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('cms_comments', ['id' => $comment->id]);
    }
}
