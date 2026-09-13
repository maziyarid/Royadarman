<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PanelDemoRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function test_signed_demo_links_log_into_each_exact_demo_identity_and_are_audited(): void
    {
        config()->set('royadarman.panel_demo_access', true);

        $users = [];
        foreach (PanelDemoRegistry::identities() as $alias => $identity) {
            $users[$alias] = User::factory()->create([
                'email' => $identity['email'],
                'role' => $identity['role']->value,
                'is_active' => true,
            ]);
        }

        foreach ($users as $alias => $user) {
            Auth::logout();
            $url = URL::temporarySignedRoute(
                'demo.panel.access',
                now()->addMinute(),
                ['role' => $alias, 'locale' => 'en'],
            );

            $this->get($url)->assertRedirect('/en/panel');
            $this->assertAuthenticatedAs($user);
            $this->assertDatabaseHas('audit_events', [
                'actor_user_id' => $user->id,
                'action' => 'demo.panel.accessed',
                'resource_type' => User::class,
                'resource_id' => (string) $user->id,
                'result' => 'success',
            ]);
            $this->assertNotNull($user->fresh()->last_authenticated_at);
        }
    }

    public function test_demo_link_refuses_identity_with_wrong_role_even_when_email_matches(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        User::factory()->create([
            'email' => 'demo-owner@royadarman.invalid',
            'role' => 'patient',
            'is_active' => true,
        ]);

        $url = URL::temporarySignedRoute('demo.panel.access', now()->addMinute(), ['role' => 'admin']);
        $this->get($url)->assertNotFound();
        $this->assertGuest();
    }

    public function test_signed_demo_link_rejects_unsupported_locale(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        User::factory()->create([
            'email' => 'demo-owner@royadarman.invalid',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $url = URL::temporarySignedRoute(
            'demo.panel.access',
            now()->addMinute(),
            ['role' => 'admin', 'locale' => 'de'],
        );
        $this->get($url)->assertNotFound();
        $this->assertGuest();
    }

    public function test_unsigned_demo_link_is_rejected(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        User::factory()->create(['email' => 'demo-owner@royadarman.invalid', 'role' => 'owner', 'is_active' => true]);
        $this->get('/__panel-test/admin')->assertForbidden();
    }

    public function test_demo_seeder_is_gated_and_idempotent_for_synthetic_records(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        config()->set('royadarman.intake_enabled', false);

        $this->artisan('royadarman:panel-demo:seed')->assertSuccessful();
        $this->artisan('royadarman:panel-demo:seed')->assertSuccessful();

        foreach (PanelDemoRegistry::identities() as $identity) {
            $this->assertDatabaseHas('users', [
                'email' => $identity['email'],
                'role' => $identity['role']->value,
                'is_active' => true,
            ]);
        }

        $this->assertSame(6, DB::table('users')->whereIn(
            'email',
            array_column(PanelDemoRegistry::identities(), 'email'),
        )->count());
        $this->assertDatabaseHas('clinics', ['name' => 'TEST Demo Clinic', 'is_active' => true]);
        $this->assertSame(2, DB::table('patient_cases')->whereIn('public_reference', [
            'TEST-DEMO-OPG-001',
            'TEST-DEMO-REF-001',
        ])->count());
        $this->assertSame(3, DB::table('case_assignments')->count());
        $this->assertSame(1, DB::table('review_revisions')->count());
        $this->assertSame(1, DB::table('referral_proposals')->count());
        $this->assertSame(1, DB::table('referral_grants')->count());
        $this->assertSame(1, DB::table('consent_events')->where('purpose', 'referral_sharing')->count());
    }

    public function test_demo_seeder_refuses_to_run_when_access_is_disabled_or_intake_is_enabled(): void
    {
        config()->set('royadarman.panel_demo_access', false);
        config()->set('royadarman.intake_enabled', false);
        $this->artisan('royadarman:panel-demo:seed')->assertFailed();

        config()->set('royadarman.panel_demo_access', true);
        config()->set('royadarman.intake_enabled', true);
        $this->artisan('royadarman:panel-demo:seed')->assertFailed();

        $this->assertSame(0, DB::table('users')->whereIn(
            'email',
            array_column(PanelDemoRegistry::identities(), 'email'),
        )->count());
    }

    public function test_demo_seeder_fails_closed_on_reserved_identity_collision_without_overwriting_credentials(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        config()->set('royadarman.intake_enabled', false);

        $existing = User::factory()->create([
            'email' => 'demo-owner@royadarman.invalid',
            'role' => 'owner',
            'is_active' => true,
        ]);
        $originalPassword = $existing->getRawOriginal('password');

        $this->artisan('royadarman:panel-demo:seed')->assertFailed();

        $existing->refresh();
        $this->assertSame($originalPassword, $existing->getRawOriginal('password'));
        $this->assertSame('owner', $existing->getRawOriginal('role'));
        $this->assertSame(1, DB::table('users')->where('email', 'demo-owner@royadarman.invalid')->count());
        $this->assertSame(0, DB::table('patient_cases')->count());
    }

    public function test_demo_link_command_is_gated_and_validates_bounds(): void
    {
        config()->set('royadarman.panel_demo_access', false);
        $this->artisan('royadarman:panel-demo:links')->assertFailed();

        config()->set('royadarman.panel_demo_access', true);
        $this->artisan('royadarman:panel-demo:links', ['--minutes' => '0'])->assertFailed();
        $this->artisan('royadarman:panel-demo:links', ['--minutes' => '15', '--locale' => 'de'])->assertFailed();
        $this->artisan('royadarman:panel-demo:links', ['--minutes' => '15', '--locale' => 'fa'])->assertSuccessful();
    }
}
