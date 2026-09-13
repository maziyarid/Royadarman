<?php

namespace Tests\Feature;

use App\Models\Cms\Category;
use App\Models\Cms\CategoryTranslation;
use App\Models\Cms\Media;
use App\Models\Cms\Post;
use App\Models\Cms\Redirect;
use App\Models\Cms\Tag;
use App\Models\Cms\TagTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CmsContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public-cms');
    }

    // --- Categories ---------------------------------------------------------

    public function test_owner_creates_hierarchical_category_with_translations(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $parent = Category::query()->create(['status' => 'active']);
        CategoryTranslation::query()->create([
            'category_id' => $parent->id, 'locale' => 'fa', 'name' => 'راهنما', 'slug' => 'guide-fa',
        ]);

        $this->actingAs($owner)->postJson('/api/v1/cms/categories', [
            'parent_id' => $parent->id,
            'translations' => [
                ['locale' => 'fa', 'name' => 'ایمپلنت', 'slug' => 'implant-fa', 'description' => 'دسته ایمپلنت'],
                ['locale' => 'en', 'name' => 'Implants', 'slug' => 'implant-en'],
            ],
        ])->assertCreated();

        $child = Category::query()->where('parent_id', $parent->id)->first();
        $this->assertNotNull($child);
        $this->assertSame(2, $child->translations()->count());
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $category = Category::query()->create(['status' => 'active']);
        CategoryTranslation::query()->create([
            'category_id' => $category->id, 'locale' => 'fa', 'name' => 'x', 'slug' => 'x-fa',
        ]);

        $this->actingAs($owner)->patchJson("/api/v1/cms/categories/{$category->id}", [
            'parent_id' => $category->id,
        ]);

        $category->refresh();
        $this->assertNull($category->parent_id);
    }

    public function test_coordinator_can_manage_categories_but_not_delete(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);

        $this->actingAs($coordinator)
            ->getJson('/api/v1/cms/categories')
            ->assertOk();

        $category = Category::query()->create(['status' => 'active']);
        CategoryTranslation::query()->create([
            'category_id' => $category->id, 'locale' => 'fa', 'name' => 'x', 'slug' => 'x-fa',
        ]);

        $this->actingAs($coordinator)
            ->deleteJson("/api/v1/cms/categories/{$category->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('cms_categories', ['id' => $category->id]);
    }

    public function test_patient_cannot_access_categories(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient)
            ->getJson('/api/v1/cms/categories')
            ->assertForbidden();
    }

    // --- Tags ----------------------------------------------------------------

    public function test_tag_merge_moves_post_tags_and_deletes_source(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $source = Tag::query()->create();
        TagTranslation::query()->create(['tag_id' => $source->id, 'locale' => 'fa', 'name' => 'old', 'slug' => 'old-fa']);
        $target = Tag::query()->create();
        TagTranslation::query()->create(['tag_id' => $target->id, 'locale' => 'fa', 'name' => 'new', 'slug' => 'new-fa']);

        $post = Post::query()->create([
            'author_user_id' => $owner->id,
            'type' => 'post',
            'status' => 'draft',
        ]);
        \DB::table('cms_post_tag')->insert([
            'post_id' => $post->id, 'tag_id' => $source->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/cms/tags/{$source->id}/merge", ['target_tag_id' => $target->id])
            ->assertOk();

        $this->assertDatabaseMissing('cms_tags', ['id' => $source->id]);
        $this->assertDatabaseHas('cms_post_tag', ['post_id' => $post->id, 'tag_id' => $target->id]);
    }

    public function test_tag_merge_into_itself_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $tag = Tag::query()->create();

        $this->actingAs($owner)
            ->postJson("/api/v1/cms/tags/{$tag->id}/merge", ['target_tag_id' => $tag->id])
            ->assertStatus(422);
    }

    // --- Redirects -----------------------------------------------------------

    public function test_redirect_duplicate_source_returns_conflict(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        Redirect::query()->create([
            'source_path' => '/old-path', 'destination_url' => '/new', 'status_code' => 301,
        ]);

        $this->actingAs($owner)->postJson('/api/v1/cms/redirects', [
            'source_path' => '/old-path', 'destination_url' => '/other', 'status_code' => 301,
        ])->assertStatus(409);
    }

    public function test_redirect_supports_301_and_302(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->postJson('/api/v1/cms/redirects', [
            'source_path' => '/a', 'destination_url' => '/b', 'status_code' => 301,
        ])->assertCreated();

        $this->actingAs($owner)->postJson('/api/v1/cms/redirects', [
            'source_path' => '/c', 'destination_url' => '/d', 'status_code' => 302,
        ])->assertCreated();

        $this->actingAs($owner)->postJson('/api/v1/cms/redirects', [
            'source_path' => '/e', 'destination_url' => '/f', 'status_code' => 410,
        ])->assertStatus(422);
    }

    // --- Media ---------------------------------------------------------------

    public function test_owner_can_upload_media_with_dimensions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($owner)->postJson('/api/v1/cms/media', [
            'file' => $file,
            'locale' => 'fa',
            'alt_text' => 'تصویر دندان',
            'caption' => 'تصویر نمونه',
        ])->assertCreated();

        $media = Media::query()->first();
        $this->assertNotNull($media);
        $this->assertSame(800, $media->width);
        $this->assertSame(600, $media->height);
        $this->assertNotNull($media->sha256);
        Storage::disk('public-cms')->assertExists($media->storage_key);
    }

    public function test_media_rejects_unsafe_file_type(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $file = UploadedFile::fake()->create('evil.exe', 100, 'application/octet-stream');

        $this->actingAs($owner)->postJson('/api/v1/cms/media', [
            'file' => $file,
            'locale' => 'en',
        ])->assertStatus(422);

        $this->assertNull(Media::query()->first());
    }

    public function test_media_destroy_removes_file_and_record(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $this->actingAs($owner)->postJson('/api/v1/cms/media', [
            'file' => $file, 'locale' => 'en',
        ])->assertCreated();

        $media = Media::query()->first();
        Storage::disk('public-cms')->assertExists($media->storage_key);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/cms/media/{$media->id}")
            ->assertOk();

        Storage::disk('public-cms')->assertMissing($media->storage_key);
        $this->assertDatabaseMissing('cms_media', ['id' => $media->id]);
    }

    public function test_patient_cannot_upload_media(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient)
            ->postJson('/api/v1/cms/media', [
                'file' => UploadedFile::fake()->image('p.jpg', 10, 10),
                'locale' => 'en',
            ])
            ->assertForbidden();
    }
}
