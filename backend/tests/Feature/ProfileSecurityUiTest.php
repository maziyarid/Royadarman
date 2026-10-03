<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\SessionAssurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class ProfileSecurityUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_uses_external_assets_and_preserves_existing_preferences_and_credentials_forms(): void
    {
        $user = $this->account('patient');
        $response = $this->actingAs($user)->get('/en/panel/profile')->assertOk();

        $response->assertSee('/assets/profile-workspace.css', false)
            ->assertSee('/assets/profile-workspace.js', false)
            ->assertSee('id="profile-preferences"', false)
            ->assertSee('id="profile-credentials"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('action="http://localhost/en/panel/profile/credentials"', false)
            ->assertDontSee('style=', false)
            ->assertDontSee('onclick=', false)
            ->assertDontSee('onsubmit=', false);
    }

    public function test_invalid_profile_submission_returns_visible_accessible_errors_without_mutating_identity(): void
    {
        $user = $this->account('patient');
        $this->actingAs($user)->from('/en/panel/profile')->patch('/en/panel/profile', [
            'name' => str_repeat('x', 81), 'locale' => 'unavailable',
        ])->assertRedirect('/en/panel/profile')->assertSessionHasErrors(['name', 'locale']);

        $errorBag = session('errors');
        $this->assertTrue($errorBag->any());
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get('/en/panel/profile')->assertOk()
            ->assertSee('id="profile-validation-errors"', false)
            ->assertSee('href="#name"', false)
            ->assertSee('href="#pref-locale"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('id="name-error"', false);
        $this->assertSame('Profile Example', $user->fresh()->name);
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_pending_authenticator_key_has_explicit_labels_and_csp_safe_selection_controls(): void
    {
        $user = $this->account('coordinator');
        $this->actingAs($user)->withSession(['pending_totp_secret' => 'GEZDGNBVGY3TQOJQ'])
            ->get('/en/panel/profile')->assertOk()
            ->assertSee('id="profile-security"', false)
            ->assertSee('for="totp-manual-key"', false)
            ->assertSee('id="totp-manual-key"', false)
            ->assertSee('data-copy-value="#totp-manual-key"', false)
            ->assertSee('data-select-value', false)
            ->assertSee('action="http://localhost/en/panel/profile/security/totp/confirm"', false)
            ->assertSee('action="http://localhost/en/panel/profile/security/totp/cancel"', false)
            ->assertDontSee('onclick=', false)
            ->assertDontSee('style=', false);
    }

    public function test_wrong_current_password_is_visible_after_redirect_and_password_fields_are_not_repopulated(): void
    {
        $user = $this->account('patient');
        $this->actingAs($user)->withSession([
            SessionAssurance::KEY => now()->timestamp, SessionAssurance::METHOD_KEY => 'password',
            'auth_method' => 'password',
        ])->from('/en/panel/profile')->post('/en/panel/profile/credentials', [
            'username' => $user->username, 'current_password' => 'wrong-current-private',
            'password' => 'new-password-private-123', 'password_confirmation' => 'new-password-private-123',
        ])->assertRedirect('/en/panel/profile')->assertSessionHasErrors('current_password');

        $this->withCookie(config('session.cookie'), session()->getId())->get('/en/panel/profile')
            ->assertOk()
            ->assertSee(__('panel.credentials.current_password_invalid'))
            ->assertSee('href="#credential-current-password"', false)
            ->assertSee('id="credential-current-password-error"', false)
            ->assertDontSee('wrong-current-private')
            ->assertDontSee('new-password-private-123');
    }

    public function test_session_inventory_actions_use_existing_external_confirmation_and_hide_other_users_sessions(): void
    {
        $user = $this->account('patient');
        $other = $this->account('patient', 'other');
        DB::table('sessions')->insert([
            ['id' => 'own-device-synthetic', 'user_id' => $user->id, 'ip_address' => '192.0.2.10', 'user_agent' => 'Firefox/1 Linux', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'other-device-synthetic', 'user_id' => $other->id, 'ip_address' => '192.0.2.20', 'user_agent' => 'Chrome/1 Windows', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($user)->get('/en/panel/profile')->assertOk()
            ->assertSee('Firefox · Linux')
            ->assertSee('data-confirm="Revoke this session?"', false)
            ->assertSee('data-confirm="Revoke every other session?', false)
            ->assertSee('action="http://localhost/en/panel/profile/sessions/revoke-all"', false)
            ->assertDontSee('192.0.2.20')
            ->assertDontSee('other-device-synthetic')
            ->assertDontSee('onsubmit=', false);
    }

    public function test_demo_profile_retains_identity_but_has_no_mutation_forms(): void
    {
        $user = $this->account('coordinator');
        config()->set('royadarman.panel_demo_access', true);
        $this->actingAs($user)->withSession(['panel_demo' => true, 'panel_demo_user_id' => (string) $user->id])->get('/en/panel/profile')
            ->assertOk()
            ->assertSee('Profile Example')
            ->assertDontSee('name="password"', false)
            ->assertDontSee('data-profile-form', false)
            ->assertDontSee('data-copy-value', false);
    }

    public function test_configured_authenticator_and_own_passkeys_remain_csp_safe_with_confirmation_and_tehran_dates(): void
    {
        $user = $this->account('coordinator');
        $user->update(['totp_secret' => 'GEZDGNBVGY3TQOJQ']);
        $other = $this->account('coordinator', 'other');
        DB::table('passkeys')->insert([
            ['user_id' => $user->id, 'name' => 'Synthetic own security key', 'credential_id' => 'own-synthetic-key', 'credential' => '{}', 'last_used_at' => '2026-09-30 22:15:00', 'created_at' => '2026-09-30 22:00:00', 'updated_at' => now()],
            ['user_id' => $other->id, 'name' => 'Unrelated private security key', 'credential_id' => 'other-synthetic-key', 'credential' => '{}', 'last_used_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)->withSession(['new_recovery_codes' => ['synthetic-one-time-code']])
            ->get('/en/panel/profile')->assertOk()
            ->assertSee('Synthetic own security key')
            ->assertDontSee('Unrelated private security key')
            ->assertSee('2026-10-01 01:30')
            ->assertSee('2026-10-01 01:45')
            ->assertSee('class="profile-recovery-codes"', false)
            ->assertSee('data-confirm="'.__('panel.profile_workspace.confirm_totp_disable').'"', false)
            ->assertSee('data-confirm="'.__('panel.profile_workspace.confirm_passkey_delete').'"', false)
            ->assertDontSee('style=', false)
            ->assertDontSee('onclick=', false)
            ->assertDontSee('onsubmit=', false);
    }

    public function test_profile_affiliations_render_only_the_supplied_safe_projection_with_a_privacy_explanation(): void
    {
        // Rendering contract only; the projection service has its own binding/privacy tests.
        View::composer('panel.profile', function ($view): void {
            $view->with('selfProfile', [
                'clinic_affiliations' => [['clinic_name' => 'Synthetic Own Clinic', 'membership_role' => 'contact', 'active_from' => '2026-01-01T00:00:00Z', 'active_until' => '2026-12-01T00:00:00Z']],
                'practitioner_credential' => ['credential_status' => 'verified', 'verified_at' => '2026-01-01T00:00:00Z', 'expires_at' => '2026-12-01T00:00:00Z'],
            ]);
        });
        $this->actingAs($this->account('clinician'))->get('/en/panel/profile')->assertOk()
            ->assertSee('Synthetic Own Clinic')
            ->assertSee(__('panel.profile_workspace.affiliation_privacy'))
            ->assertSee(__('panel.profile_workspace.credential_verified'))
            ->assertSee('2026-12-01')
            ->assertDontSee('licence_number');
    }

    public function test_all_supported_locales_render_profile_labels_without_unresolved_translation_keys(): void
    {
        $user = $this->account('patient');
        foreach (['fa', 'ar', 'en'] as $locale) {
            $this->actingAs($user)->get('/'.$locale.'/panel/profile')->assertOk()
                ->assertSee('id="profile-preferences"', false)
                ->assertDontSee('panel.profile_workspace.');
        }
    }

    private function account(string $role, string $suffix = 'own'): User
    {
        return User::factory()->create([
            'role' => $role, 'name' => 'Profile Example', 'locale' => 'en',
            'phone_hash' => hash('sha256', 'profile-'.$suffix),
            'username' => 'profile.'.$suffix, 'password' => 'synthetic-Password-123',
        ]);
    }
}
