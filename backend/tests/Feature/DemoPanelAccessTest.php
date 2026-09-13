<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class DemoPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_panel_access_is_disabled_by_default(): void
    {
        $url = URL::temporarySignedRoute('demo.panel.access', now()->addMinute(), ['role' => 'admin']);
        $this->get($url)->assertNotFound();
    }

    public function test_signed_demo_link_logs_into_exact_demo_identity(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        $user = User::factory()->create([
            'email' => 'demo-owner@royadarman.invalid',
            'role' => 'owner',
            'is_active' => true,
        ]);
        $url = URL::temporarySignedRoute('demo.panel.access', now()->addMinute(), ['role' => 'admin']);
        $this->get($url)->assertRedirect('/fa/panel');
        $this->assertAuthenticatedAs($user);
    }

    public function test_unsigned_demo_link_is_rejected(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        User::factory()->create(['email' => 'demo-owner@royadarman.invalid', 'role' => 'owner', 'is_active' => true]);
        $this->get('/__panel-test/admin')->assertForbidden();
    }
}
