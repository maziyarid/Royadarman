<?php

namespace Tests\Feature;

use App\Models\Cms\Category;
use App\Models\Cms\Media;
use App\Models\Cms\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Tests\TestCase;

final class AdminCmsContentUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_categories(): void
    {
        $this->get('/admin/cms/categories')->assertRedirect('/fa/');
    }

    public function test_patient_is_forbidden_from_categories(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $this->actingAs($patient)->get('/admin/cms/categories')->assertForbidden();
    }

    public function test_owner_can_list_categories(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/cms/categories')
            ->assertOk()
            ->assertSee(__('ui.admin.categories'), false)
            ->assertSee(__('ui.admin.empty'), false);
    }

    public function test_owner_can_create_category(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/cms/categories/create')
            ->assertOk()
            ->assertSee(__('ui.admin.new_category'), false);

        $this->actingAs($owner)->post('/admin/cms/categories', [
            'parent_id' => null,
            'translations' => [
                ['locale' => 'fa', 'name' => 'ایمپلنت', 'slug' => 'implant-fa', 'description' => 'توضیح'],
                ['locale' => 'en', 'name' => 'Implants', 'slug' => 'implants-en', 'description' => null],
            ],
        ])->assertRedirect();

        $this->assertSame(1, Category::count());
        $this->assertSame(2, Category::first()->translations()->count());
    }

    public function test_owner_can_list_tags_and_create(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/cms/tags')
            ->assertOk()
            ->assertSee(__('ui.admin.tags'), false);

        $this->actingAs($owner)->post('/admin/cms/tags', [
            'translations' => [
                ['locale' => 'fa', 'name' => 'کاشت دندان', 'slug' => 'implant-tag'],
            ],
        ])->assertRedirect();

        $this->assertSame(1, Tag::count());
    }

    public function test_owner_can_list_media(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/cms/media')
            ->assertOk()
            ->assertSee(__('ui.admin.media'), false)
            ->assertSee(__('ui.admin.upload'), false);
    }

    public function test_owner_can_upload_media(): void
    {
        \Storage::fake('public-cms');
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->post('/admin/cms/media', [
            'file' => File::image('test.png', 100, 100),
        ])->assertRedirect();

        $this->assertSame(1, Media::count());
        \Storage::disk('public-cms')->assertExists(Media::first()->storage_key);
    }

    public function test_coordinator_can_manage_but_not_delete_category(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($coordinator)->get('/admin/cms/categories')->assertOk();
        $this->actingAs($coordinator)->get('/admin/cms/categories/create')->assertOk();

        $category = Category::create();
        $category->translations()->create(['locale' => 'fa', 'name' => 'x', 'slug' => 'x']);

        $this->actingAs($coordinator)
            ->delete('/admin/cms/categories/'.$category->id)
            ->assertForbidden();
    }
}
