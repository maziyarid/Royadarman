<?php

namespace Tests\Feature;

use App\Models\Cms\Post;
use App\Models\Cms\SeoMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCmsSeoUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_seo(): void
    {
        $this->get('/admin/cms/seo')->assertRedirect('/fa/');
    }

    public function test_patient_is_forbidden_from_seo(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $this->actingAs($patient)->get('/admin/cms/seo')->assertForbidden();
    }

    public function test_owner_can_list_seo(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/cms/seo')
            ->assertOk()
            ->assertSee(__('ui.admin.seo'), false)
            ->assertSee(__('ui.admin.empty'), false);
    }

    public function test_owner_can_edit_and_save_post_seo(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $post = Post::create(['author_user_id' => $owner->id, 'type' => 'post', 'status' => 'published']);
        $post->translations()->create(['locale' => 'fa', 'title' => 'تست', 'slug' => 'test', 'body' => '<p>x</p>', 'sanitized_body' => '<p>x</p>']);

        $this->actingAs($owner)->get('/admin/cms/seo/'.$post->id.'/edit')
            ->assertOk()
            ->assertSee('تست', false);

        $this->actingAs($owner)->patch('/admin/cms/seo/'.$post->id, [
            'seo' => [
                ['locale' => 'fa', 'seo_title' => 'عنوان سئو', 'meta_description' => 'توضیح متا', 'canonical_url' => null, 'robots_directive' => 'index, follow', 'og_title' => null, 'og_description' => null, 'focus_keyword' => 'ایمپلنت'],
                ['locale' => 'en', 'seo_title' => 'SEO title', 'meta_description' => 'meta desc', 'canonical_url' => null, 'robots_directive' => 'index, follow', 'og_title' => null, 'og_description' => null, 'focus_keyword' => 'implant'],
            ],
        ])->assertRedirect();

        $this->assertSame(2, SeoMetadata::where('entity_type', 'App\\Models\\Cms\\Post')->where('entity_id', $post->id)->count());
        $this->assertSame('ایمپلنت', SeoMetadata::where('entity_id', $post->id)->where('locale', 'fa')->first()->focus_keyword);
    }
}
