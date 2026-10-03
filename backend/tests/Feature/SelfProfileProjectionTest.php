<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Practitioner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class SelfProfileProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_and_web_project_only_own_current_active_clinic_affiliations(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['role' => 'clinic_rep']);
        $other = User::factory()->create(['role' => 'clinic_rep']);
        $own = $this->membership($user, 'Own active clinic', ['active_from' => now(), 'active_until' => now()->addDay()]);
        $this->membership($other, 'FOREIGN-CLINIC', ['active_until' => now()->addDay()]);
        $this->membership($user, 'FUTURE-CLINIC', ['active_from' => now()->addSecond()]);
        $this->membership($user, 'EXPIRED-CLINIC', ['active_until' => now()]);
        $inactive = $this->membership($user, 'INACTIVE-CLINIC');
        $inactive->clinic()->update(['is_active' => false]);

        $api = $this->actingAs($user)->getJson('/api/v1/me?user_id='.$other->id)->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonCount(1, 'data.clinic_affiliations')->assertJsonPath('data.clinic_affiliations.0.clinic_name', 'Own active clinic');
        $this->assertStringContainsString('no-store', $api->headers->get('Cache-Control'));
        $this->assertSame(['clinic_name', 'membership_role', 'active_from', 'active_until'], array_keys($api->json('data.clinic_affiliations.0')));
        foreach (['FOREIGN-CLINIC', 'FUTURE-CLINIC', 'EXPIRED-CLINIC', 'INACTIVE-CLINIC'] as $name) {
            $api->assertDontSee($name);
        }
        $page = $this->get('/en/panel/profile')->assertOk()->assertViewHas('selfProfile');
        $page->assertSee('Own active clinic')->assertDontSee('FOREIGN-CLINIC')->assertDontSee('FUTURE-CLINIC')->assertDontSee('EXPIRED-CLINIC')->assertDontSee('INACTIVE-CLINIC');
        $this->assertSame('Own active clinic', $page->viewData('selfProfile')['clinic_affiliations'][0]['clinic_name']);
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $own->update(['active_until' => now()]);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonCount(0, 'data.clinic_affiliations');
    }

    public function test_own_credential_projection_excludes_licence_secrets_and_other_practitioners(): void
    {
        $user = User::factory()->create(['role' => 'clinician', 'totp_secret' => 'PRIVATE-TOTP']);
        $other = User::factory()->create(['role' => 'clinician']);
        foreach ([$user, $other] as $subject) {
            Practitioner::query()->create(['user_id' => $subject->id, 'licence_number' => 'PRIVATE-LICENCE-'.$subject->id, 'licence_hash' => hash('sha256', (string) $subject->id), 'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        }
        $api = $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.practitioner_credential.credential_status', 'verified');
        $this->assertSame(['credential_status', 'verified_at', 'expires_at'], array_keys($api->json('data.practitioner_credential')));
        $api->assertDontSee('PRIVATE-LICENCE')->assertDontSee('PRIVATE-TOTP');
        foreach (['password', 'totp_secret', 'mfa_recovery_codes', 'phone', 'phone_hash', 'licence_number', 'licence_hash'] as $key) {
            $this->assertArrayNotHasKey($key, $api->json('data'));
            $this->assertArrayNotHasKey($key, $api->json('data.practitioner_credential'));
        }
    }

    public function test_legacy_roles_can_read_their_empty_self_profile_without_role_or_grant_activation(): void
    {
        foreach (['patient', 'coordinator', 'clinician', 'clinic_rep', 'owner', 'tech_admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.role', $role)->assertJsonCount(0, 'data.clinic_affiliations')->assertJsonPath('data.practitioner_credential', null);
        }
    }

    public function test_api_preferences_are_audited_without_values_or_privilege_changes(): void
    {
        $user = User::factory()->create(['role' => 'patient']);
        $this->actingAs($user)->patchJson('/api/v1/me/preferences', ['name' => 'PRIVATE-NEW-NAME', 'locale' => 'ar', 'role' => 'owner'])->assertOk()->assertJsonPath('data.name', 'PRIVATE-NEW-NAME')->assertJsonPath('data.locale', 'ar')->assertJsonPath('data.role', 'patient');
        $audit = AuditEvent::query()->where('action', 'profile.preferences.updated')->sole();
        $this->assertSame($user->id, $audit->actor_user_id);
        $this->assertSame((string) $user->id, $audit->resource_id);
        $this->assertSame(['locale', 'name'], $audit->context['fields']);
        $this->assertStringNotContainsString('PRIVATE-NEW-NAME', json_encode($audit->context, JSON_THROW_ON_ERROR));
        $this->assertArrayNotHasKey('role', $audit->context);
    }

    public function test_web_preferences_use_the_same_audit_and_validation_does_not_write(): void
    {
        $user = User::factory()->create(['role' => 'patient']);
        $this->actingAs($user)->patch('/en/panel/profile', ['name' => 'Synthetic new name', 'locale' => 'fa'])->assertRedirect('/fa/panel/profile');
        $this->assertDatabaseCount('audit_events', 1);
        $this->patchJson('/api/v1/me/preferences', ['locale' => 'invalid'])->assertUnprocessable();
        $this->assertSame('fa', $user->refresh()->locale);
        $this->assertDatabaseCount('audit_events', 1);
        $this->patchJson('/api/v1/me/preferences', [])->assertOk();
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_audit_storage_failure_rolls_back_profile_update(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'name' => 'Original name']);
        AuditEvent::creating(function () {
            throw new RuntimeException('Synthetic audit failure');
        });
        $this->actingAs($user)->patchJson('/api/v1/me/preferences', ['name' => 'Changed name'])->assertStatus(500);
        $this->assertSame('Original name', $user->refresh()->name);
        $this->assertDatabaseCount('audit_events', 0);
    }

    private function membership(User $user, string $name, array $dates = []): ClinicMembership
    {
        $clinic = Clinic::query()->create(['name' => $name, 'city' => 'Tehran', 'is_active' => true]);

        return ClinicMembership::query()->create(['user_id' => $user->id, 'clinic_id' => $clinic->id, 'membership_role' => 'contact', 'active_from' => $dates['active_from'] ?? now()->subDay(), 'active_until' => $dates['active_until'] ?? null]);
    }
}
