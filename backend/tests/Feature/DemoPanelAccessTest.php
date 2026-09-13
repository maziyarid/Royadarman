<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PanelDemoRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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

    public function test_demo_session_is_read_only_and_cannot_reach_privileged_web_or_api_routes(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        config()->set('royadarman.intake_enabled', false);
        $this->artisan('royadarman:panel-demo:seed')->assertSuccessful();

        $url = URL::temporarySignedRoute('demo.panel.access', now()->addMinute(), ['role' => 'admin', 'locale' => 'fa']);
        $this->get($url)->assertRedirect('/fa/panel');

        $this->get('/fa/panel')
            ->assertOk()
            ->assertSee('TEST')
            ->assertDontSee('/admin/cms/posts', false)
            ->assertDontSee('/fa/panel/marketing', false)
            ->assertDontSee('/fa/panel/network', false);

        $this->get('/admin/cms/posts')->assertForbidden();
        $this->getJson('/api/v1/me')->assertForbidden();
        $this->postJson('/api/v1/support', ['message' => 'should never be processed'])->assertForbidden();

        $this->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('data.logged_out', true);
        $this->assertGuest();
    }

    public function test_turning_off_demo_access_invalidates_an_existing_demo_session(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        config()->set('royadarman.intake_enabled', false);
        $this->artisan('royadarman:panel-demo:seed')->assertSuccessful();

        $url = URL::temporarySignedRoute('demo.panel.access', now()->addMinute(), ['role' => 'client']);
        $this->get($url)->assertRedirect('/fa/panel');
        $this->assertAuthenticated();

        config()->set('royadarman.panel_demo_access', false);
        $this->get('/fa/panel')->assertForbidden();
        $this->assertGuest();
    }

    public function test_demo_panels_hide_non_test_cases_even_when_a_demo_user_is_accidentally_assigned(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        config()->set('royadarman.intake_enabled', false);
        $this->artisan('royadarman:panel-demo:seed')->assertSuccessful();

        $coordinator = User::query()->where('email', 'demo-coordinator@royadarman.invalid')->firstOrFail();
        $caseId = (string) Str::ulid();
        DB::table('patient_cases')->insert([
            'id' => $caseId,
            'public_reference' => 'REAL-CASE-MUST-NOT-LEAK',
            'patient_user_id' => null,
            'service_type' => 'guidance_referral',
            'status' => 'in_coordination',
            'priority' => 'normal',
            'patient_name' => null,
            'patient_mobile' => encrypt('09120000000'),
            'patient_mobile_hash' => hash('sha256', '09120000000'),
            'tehran_area' => 'central',
            'preferred_contact_time' => null,
            'contact_reason' => null,
            'budget_band' => 'unknown',
            'current_coordinator_id' => $coordinator->id,
            'submitted_at' => now(),
            'closed_at' => null,
            'version' => 1,
            'source_language' => 'fa',
            'currency' => 'IRR',
            'budget_input_unit' => 'toman',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $caseId,
            'assignee_user_id' => $coordinator->id,
            'assigned_by_user_id' => $coordinator->id,
            'purpose' => 'coordination',
            'assigned_at' => now(),
            'released_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('demo.panel.access', now()->addMinute(), ['role' => 'coordinator']);
        $this->get($url)->assertRedirect('/fa/panel');
        $this->get('/fa/panel')
            ->assertOk()
            ->assertSee('TEST-DEMO-OPG-001')
            ->assertSee('TEST-DEMO-REF-001')
            ->assertDontSee('REAL-CASE-MUST-NOT-LEAK');
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
