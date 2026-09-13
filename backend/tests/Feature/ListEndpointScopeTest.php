<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ListEndpointScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function cmsListRoutes(): array
    {
        return [
            'cms posts' => ['/api/v1/cms/posts', ['patient', 'clinician', 'clinic_rep']],
            'cms categories' => ['/api/v1/cms/categories', ['patient', 'clinician', 'clinic_rep']],
            'cms tags' => ['/api/v1/cms/tags', ['patient', 'clinician', 'clinic_rep']],
            'cms media' => ['/api/v1/cms/media', ['patient', 'clinician', 'clinic_rep']],
            'cms menus' => ['/api/v1/cms/menus', ['patient', 'clinician', 'clinic_rep']],
            'cms redirects' => ['/api/v1/cms/redirects', ['patient', 'clinician', 'clinic_rep']],
            'cms seo' => ['/api/v1/cms/seo', ['patient', 'clinician', 'clinic_rep']],
            'cms comments' => ['/api/v1/cms/comments', ['patient', 'clinician', 'clinic_rep']],
        ];
    }

    #[DataProvider('cmsListRoutes')]
    public function test_non_cms_roles_are_denied_on_cms_list_endpoints(string $uri, array $deniedRoles): void
    {
        foreach ($deniedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)
                ->getJson($uri)
                ->assertForbidden();
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function cmsManageRoles(): array
    {
        return [
            'owner' => ['owner'],
            'tech admin' => ['tech_admin'],
            'coordinator' => ['coordinator'],
        ];
    }

    #[DataProvider('cmsManageRoles')]
    public function test_cms_roles_can_access_cms_post_list(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)
            ->getJson('/api/v1/cms/posts')
            ->assertOk();
    }

    public function test_dashboard_endpoint_scope_denies_unauthenticated(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_support_list_is_role_scoped_and_denies_clinician_and_clinic_rep(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->actingAs($clinician)
            ->getJson('/api/v1/support')
            ->assertForbidden();

        $clinicRep = User::factory()->create(['role' => 'clinic_rep']);
        $this->actingAs($clinicRep)
            ->getJson('/api/v1/support')
            ->assertForbidden();
    }

    public function test_public_cms_routes_do_not_require_auth(): void
    {
        $this->get('/')->assertOk();
        $this->get('/ar/')->assertOk();
        $this->get('/en/')->assertOk();
        $this->get('/robots.txt')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_cms_post_list_returns_empty_for_authorized_user_without_posts(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)
            ->getJson('/api/v1/cms/posts')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.last', 1);
    }
}
