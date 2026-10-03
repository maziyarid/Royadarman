<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\SessionAssurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdministratorSecurityUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Production uses MariaDB FIELD for ordering; emulate only that function
            // in this isolated SQLite fixture, without replacing the HTTP binding.
            DB::connection()->getPdo()->sqliteCreateFunction('FIELD', static function ($value, ...$values): int {
                $position = array_search($value, $values, true);

                return $position === false ? 0 : $position + 1;
            });
        }
    }

    public function test_owner_staff_directory_is_csp_safe_and_preserves_all_existing_form_bindings(): void
    {
        $owner = $this->account('owner', 'owner');
        $staff = $this->account('coordinator', 'staff');
        $response = $this->actingAs($owner)->get('/en/panel/administrators')->assertOk();

        $response->assertSee('/assets/administrator-workspace.css', false)
            ->assertSee('id="administrator-add"', false)
            ->assertSee('id="administrator-staff"', false)
            ->assertSee('action="http://localhost/en/panel/administrators"', false)
            ->assertSee('action="http://localhost/en/panel/administrators/'.$staff->id.'"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertDontSee('style=', false)
            ->assertDontSee('onsubmit=', false)
            ->assertDontSee('onclick=', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1>'));
    }

    public function test_security_actions_have_external_confirmation_and_recent_authentication_guidance(): void
    {
        $owner = $this->account('owner', 'owner');
        $staff = $this->account('coordinator', 'staff');

        $this->actingAs($owner)->get('/en/panel/administrators')->assertOk()
            ->assertSee(__('panel.security.reauthenticate'))
            ->assertSee('action="http://localhost/en/panel/administrators/'.$staff->id.'/reset-mfa"', false)
            ->assertSee('action="http://localhost/en/panel/administrators/'.$staff->id.'/revoke-sessions"', false)
            ->assertSee('data-confirm="'.__('administrators.confirm_mfa_reset').'"', false)
            ->assertSee('data-confirm="'.__('administrators.confirm_revoke_sessions', ['name' => $staff->name]).'"', false)
            ->assertDontSee('onsubmit=', false);
    }

    public function test_creation_validation_errors_are_linked_to_the_creation_fields_without_creating_a_staff_account(): void
    {
        $owner = $this->account('owner', 'owner');
        $this->actingAs($owner)->withSession($this->freshSession())->from('/en/panel/administrators')
            ->post('/en/panel/administrators', [
                '_staff_form' => 'create', 'name' => '', 'mobile' => '', 'role' => 'patient', 'locale' => 'en',
            ])->assertRedirect('/en/panel/administrators')->assertSessionHasErrors(['name', 'mobile', 'role']);

        $this->withCookie(config('session.cookie'), session()->getId())->get('/en/panel/administrators')->assertOk()
            ->assertSee('id="administrator-validation-errors"', false)
            ->assertSee('href="#staff-name"', false)
            ->assertSee('href="#staff-mobile"', false)
            ->assertSee('id="staff-name-error"', false)
            ->assertSee('aria-invalid="true"', false);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_edit_validation_errors_keep_the_affected_staff_controls_open_and_leave_other_fields_alone(): void
    {
        $owner = $this->account('owner', 'owner');
        $staff = $this->account('coordinator', 'staff');
        $this->actingAs($owner)->withSession($this->freshSession())->from('/en/panel/administrators')
            ->patch('/en/panel/administrators/'.$staff->id, [
                '_staff_form' => 'edit-'.$staff->id, 'name' => '', 'role' => 'coordinator', 'locale' => 'en', 'is_active' => '1',
            ])->assertRedirect('/en/panel/administrators')->assertSessionHasErrors('name');

        $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/en/panel/administrators')->assertOk();
        $response->assertSee('href="#staff-'.$staff->id.'-name"', false)
            ->assertSee('id="staff-'.$staff->id.'-name-error"', false)
            ->assertDontSee('id="staff-name-error"', false);
        $this->assertMatchesRegularExpression('/id="staff-'.$staff->id.'-controls"\s+open/', $response->getContent());
        $this->assertSame('Synthetic staff', $staff->fresh()->name);
    }

    public function test_current_account_and_reserved_demo_identity_have_no_management_forms_and_phone_numbers_are_masked(): void
    {
        $owner = $this->account('owner', 'owner');
        $demo = $this->account('coordinator', 'demo');
        $demo->update(['email' => 'synthetic-demo@royadarman.invalid']);

        $this->actingAs($owner)->get('/en/panel/administrators')->assertOk()
            ->assertSee('0912***0123')
            ->assertDontSee('09123450123')
            ->assertDontSee('action="http://localhost/en/panel/administrators/'.$owner->id.'"', false)
            ->assertDontSee('action="http://localhost/en/panel/administrators/'.$demo->id.'"', false)
            ->assertDontSee('/'.$demo->id.'/reset-mfa', false)
            ->assertDontSee('/'.$owner->id.'/revoke-sessions', false);
    }

    public function test_non_owner_or_inactive_owner_cannot_read_the_staff_directory(): void
    {
        foreach (['patient', 'coordinator', 'clinician', 'clinic_rep', 'tech_admin'] as $role) {
            $this->actingAs($this->account($role, $role))->get('/en/panel/administrators')->assertForbidden();
        }
        $owner = $this->account('owner', 'inactive');
        $owner->update(['is_active' => false]);
        $this->actingAs($owner)->get('/en/panel/administrators')->assertForbidden();
    }

    public function test_supported_locales_have_explicit_edit_field_labels_without_unresolved_translation_keys(): void
    {
        $owner = $this->account('owner', 'owner');
        $staff = $this->account('coordinator', 'staff');
        foreach (['fa', 'ar', 'en'] as $locale) {
            $this->actingAs($owner)->get('/'.$locale.'/panel/administrators')->assertOk()
                ->assertSee('for="staff-'.$staff->id.'-name"', false)
                ->assertSee('for="staff-'.$staff->id.'-role"', false)
                ->assertSee('for="staff-'.$staff->id.'-locale"', false)
                ->assertSee('for="staff-'.$staff->id.'-active"', false)
                ->assertDontSee('administrators.confirm_revoke_sessions')
                ->assertDontSee('administrators.reauthentication_title');
        }
    }

    private function account(string $role, string $suffix): User
    {
        return User::factory()->create([
            'role' => $role, 'name' => 'Synthetic '.$suffix, 'locale' => 'en',
            'phone' => '09123450123', 'phone_hash' => hash('sha256', 'administrator-ui-'.$suffix),
            'email' => 'administrator-ui-'.$suffix.'@example.test',
        ]);
    }

    private function freshSession(): array
    {
        return [SessionAssurance::KEY => now()->timestamp, SessionAssurance::METHOD_KEY => 'password'];
    }
}
