<?php

namespace Tests\Feature;

use App\Domain\CMS\Enums\CommentStatus;
use App\Models\Cms\Comment;
use App\Models\Cms\Menu;
use App\Models\Cms\Post;
use App\Models\Cms\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCmsOpsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_menus(): void
    {
        $this->get('/admin/cms/menus')->assertRedirect('/fa/');
    }

    public function test_patient_is_forbidden_from_redirects(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $this->actingAs($patient)->get('/admin/cms/redirects')->assertForbidden();
    }

    public function test_owner_can_list_menus(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/cms/menus')
            ->assertOk()
            ->assertSee(__('ui.admin.menus'), false);
    }

    public function test_owner_can_create_menu_and_item(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->post('/admin/cms/menus', [
            'slug' => 'main', 'location' => 'header', 'title_fa' => 'منوی اصلی',
        ])->assertRedirect();

        $this->assertSame(1, Menu::count());

        $menu = Menu::first();
        $this->actingAs($owner)->post("/admin/cms/menus/{$menu->id}/items", [
            'label_fa' => 'خانه', 'url' => '/fa/', 'parent_id' => null, 'sort_order' => 0,
        ])->assertRedirect();

        $this->assertSame(1, $menu->items()->count());
    }

    public function test_owner_can_create_redirect(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/admin/cms/redirects')->assertOk();
        $this->actingAs($owner)->post('/admin/cms/redirects', [
            'source_path' => '/old', 'destination_url' => '/fa/new', 'status_code' => '301',
        ])->assertRedirect();

        $this->assertSame(1, Redirect::count());
    }

    public function test_duplicate_source_path_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Redirect::create(['source_path' => '/dup', 'destination_url' => '/fa/x', 'status_code' => 301]);

        $this->actingAs($owner)->post('/admin/cms/redirects', [
            'source_path' => '/dup', 'destination_url' => '/fa/y', 'status_code' => '301',
        ])->assertSessionHasErrors('source_path');
    }

    public function test_owner_can_list_comments_and_approve(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $post = Post::create(['author_user_id' => $owner->id, 'type' => 'post', 'status' => 'published']);
        $comment = Comment::create(['post_id' => $post->id, 'body' => 'hello', 'status' => 'pending', 'author_name' => 'A']);

        $this->actingAs($owner)->get('/admin/cms/comments')->assertOk()->assertSee('hello');
        $this->actingAs($owner)->post("/admin/cms/comments/{$comment->id}/approve")->assertRedirect();
        $this->assertSame(CommentStatus::Approved, $comment->fresh()->status);
    }

    public function test_coordinator_can_approve_comment_but_not_delete(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $owner = User::factory()->create(['role' => 'owner']);
        $post = Post::create(['author_user_id' => $owner->id, 'type' => 'post', 'status' => 'published']);
        $comment = Comment::create(['post_id' => $post->id, 'body' => 'x', 'status' => 'pending', 'author_name' => 'B']);

        $this->actingAs($coordinator)->post("/admin/cms/comments/{$comment->id}/approve")->assertRedirect();
        $this->actingAs($coordinator)->delete("/admin/cms/comments/{$comment->id}")->assertForbidden();
    }
}
