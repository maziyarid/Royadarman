<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PanelDemoRegistry;
use App\Support\PublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class PresentationPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pres_portal_is_available_outside_production(): void
    {
        $this->get('/pres')
            ->assertOk()
            ->assertSee(__('ui.pres.title'), false)
            ->assertSee(__('ui.staging_banner'), false)
            ->assertSee(__('ui.pres.roles.client'), false)
            ->assertDontSee('/__panel-test/', false);
    }

    public function test_pres_is_hidden_in_production_without_demo_access(): void
    {
        $this->app['env'] = 'production';
        config(['royadarman.panel_demo_access' => false]);

        $this->get('/pres')->assertNotFound();
    }

    public function test_pres_mints_temporary_signed_handoffs_when_demo_access_is_on(): void
    {
        config(['royadarman.panel_demo_access' => true]);

        $html = $this->get('/pres')->assertOk()->getContent();

        $this->assertStringContainsString('/__panel-test/client', $html);
        $this->assertStringContainsString('signature=', $html);
        $this->assertStringContainsString('expires=', $html);
    }

    public function test_anonymous_visitor_cannot_reseed(): void
    {
        $response = $this->post('/pres/reseed');
        $this->assertGuest();
        $this->assertTrue($response->isRedirection() || $response->isClientError());
    }

    public function test_patient_cannot_reseed(): void
    {
        config(['royadarman.panel_demo_access' => true]);
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient)->post('/pres/reseed')->assertForbidden();
    }

    public function test_signed_demo_link_from_portal_still_requires_exact_identity(): void
    {
        config(['royadarman.panel_demo_access' => true]);
        $url = URL::temporarySignedRoute('demo.panel.access', now()->addMinute(), ['role' => PanelDemoRegistry::aliases()[0]]);
        $this->get($url)->assertNotFound();
    }

    public function test_public_url_helper_knows_the_pres_portal(): void
    {
        $this->assertSame(url('/pres'), PublicUrl::to('pres', 'fa'));
        $this->assertSame(url('/pres').'?locale=en', PublicUrl::to('pres', 'en'));
    }
}
