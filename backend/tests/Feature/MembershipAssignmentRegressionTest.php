<?php

namespace Tests\Feature;

use App\Domain\Identity\Authorization\MembershipPermissionMap;
use App\Domain\Support\Enums\ConversationStatus;
use App\Domain\Support\Enums\SupportCategory;
use App\Models\ClinicMembership;
use App\Models\PatientCase;
use App\Models\Practitioner;
use App\Models\SupportConversation;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Synthetic identities only. Does not seed production.
 * SQLite via phpunit.xml is not a MariaDB race proof.
 */
class MembershipAssignmentRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'is_active' => true]);
    }

    private function clinicId(User $owner, string $name = 'Partner Clinic'): string
    {
        $this->actingAs($owner)->post('/en/panel/network/clinics', [
            'name' => $name,
            'city' => 'Tehran',
            'area_code' => 'central',
        ])->assertRedirect();

        $id = DB::table('clinics')->where('name', $name)->value('id');
        $this->assertNotEmpty($id);

        return (string) $id;
    }

    private function verifyClinician(User $owner, User $clinician, string $status = 'verified', ?string $expiresAt = 'future'): void
    {
        $expires = match ($expiresAt) {
            'future' => now()->addYear()->format('Y-m-d H:i:s'),
            'past' => now()->subDay()->format('Y-m-d H:i:s'),
            default => null,
        };

        $this->actingAs($owner)->post('/en/panel/network/practitioners/'.$clinician->id, [
            'licence_number' => 'DEN-'.$clinician->id,
            'credential_status' => $status,
            'expires_at' => $expires,
        ])->assertRedirect();
    }

    public function test_guest_and_non_owner_cannot_assign_a_membership(): void
    {
        $owner = $this->owner();
        $clinicId = $this->clinicId($owner);
        $clinician = User::factory()->create(['role' => 'clinician', 'is_active' => true]);
        $payload = [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'contact',
        ];

        $this->flushSession();
        auth()->logout();
        $this->post('/en/panel/network/memberships', $payload)->assertRedirect('/fa/login');

        foreach (['patient', 'coordinator', 'clinician', 'clinic_rep', 'tech_admin'] as $role) {
            $caller = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($caller)->post('/en/panel/network/memberships', $payload)->assertForbidden();
        }

        $this->assertSame(0, DB::table('clinic_memberships')->count());
    }

    public function test_inactive_owner_cannot_assign_a_membership(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => false]);
        $activeOwner = $this->owner();
        $clinicId = $this->clinicId($activeOwner);
        $clinician = User::factory()->create(['role' => 'clinician', 'is_active' => true]);

        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'contact',
        ])->assertForbidden();

        $this->assertSame(0, DB::table('clinic_memberships')->count());
    }

    public function test_contact_is_allowed_for_clinician_and_clinic_representative_without_a_credential(): void
    {
        $owner = $this->owner();
        $clinicId = $this->clinicId($owner);
        $clinician = User::factory()->create(['role' => 'clinician', 'is_active' => true]);
        $representative = User::factory()->create(['role' => 'clinic_rep', 'is_active' => true]);

        foreach ([$clinician, $representative] as $member) {
            $this->actingAs($owner)->post('/en/panel/network/memberships', [
                'clinic_id' => $clinicId,
                'user_id' => $member->id,
                'membership_role' => 'contact',
            ])->assertRedirect();
        }

        $this->assertSame(2, DB::table('clinic_memberships')->where('clinic_id', $clinicId)->count());
        $this->assertDatabaseHas('clinic_memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'contact',
        ]);
    }

    public function test_reviewer_requires_a_current_verified_clinician_credential(): void
    {
        $owner = $this->owner();
        $clinicId = $this->clinicId($owner);
        $clinician = User::factory()->create(['role' => 'clinician', 'is_active' => true]);
        $representative = User::factory()->create(['role' => 'clinic_rep', 'is_active' => true]);

        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $representative->id,
            'membership_role' => 'reviewer',
        ])->assertStatus(422);

        $this->verifyClinician($owner, $clinician, 'pending');
        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
        ])->assertStatus(422);

        $this->verifyClinician($owner, $clinician, 'revoked', 'future');
        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
        ])->assertStatus(422);

        $this->verifyClinician($owner, $clinician, 'verified', 'past');
        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
        ])->assertStatus(422);

        $this->verifyClinician($owner, $clinician, 'verified', null);
        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
            'active_until' => now()->addYear()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertDatabaseHas('clinic_memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
        ]);
        $this->assertDatabaseMissing('clinic_memberships', [
            'user_id' => $representative->id,
        ]);
    }

    public function test_inactive_or_disallowed_account_roles_are_rejected(): void
    {
        $owner = $this->owner();
        $clinicId = $this->clinicId($owner);
        $inactive = User::factory()->create(['role' => 'clinician', 'is_active' => false]);
        $this->verifyClinician($owner, $inactive, 'verified', null);

        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $inactive->id,
            'membership_role' => 'reviewer',
        ])->assertStatus(422);

        foreach (['patient', 'coordinator', 'owner', 'tech_admin'] as $role) {
            $member = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($owner)->post('/en/panel/network/memberships', [
                'clinic_id' => $clinicId,
                'user_id' => $member->id,
                'membership_role' => 'contact',
            ])->assertStatus(422);
        }

        $this->actingAs($owner)->from('/en/panel/network')->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $inactive->id,
            'membership_role' => 'owner',
        ])->assertRedirect('/en/panel/network')->assertSessionHasErrors('membership_role');

        $this->assertSame(0, DB::table('clinic_memberships')->count());
    }

    public function test_repeat_assignment_updates_the_same_row_and_does_not_write_another_clinic(): void
    {
        $owner = $this->owner();
        $first = $this->clinicId($owner, 'Clinic North');
        $second = $this->clinicId($owner, 'Clinic South');
        $clinician = User::factory()->create(['role' => 'clinician', 'is_active' => true]);

        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $first,
            'user_id' => $clinician->id,
            'membership_role' => 'contact',
        ])->assertRedirect();

        $this->verifyClinician($owner, $clinician, 'verified', null);
        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $first,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
        ])->assertRedirect();

        $this->assertSame(1, DB::table('clinic_memberships')->where('clinic_id', $first)->where('user_id', $clinician->id)->count());
        $this->assertDatabaseHas('clinic_memberships', [
            'clinic_id' => $first,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
        ]);
        $this->assertDatabaseMissing('clinic_memberships', [
            'clinic_id' => $second,
            'user_id' => $clinician->id,
        ]);
    }

    public function test_demo_seed_membership_matches_the_map_and_stays_idempotent(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        config()->set('royadarman.intake_enabled', false);

        $this->artisan('royadarman:panel-demo:seed')->assertSuccessful();
        $this->artisan('royadarman:panel-demo:seed')->assertSuccessful();

        $clinicUser = User::query()->where('email', 'demo-clinic@royadarman.invalid')->firstOrFail();
        $clinicId = DB::table('clinics')->where('synthetic_demo_key', PanelDemoRegistry::CLINIC_DEMO_KEY)->value('id');
        $decision = MembershipPermissionMap::assignMembership(
            $clinicUser->role,
            'contact',
            (bool) $clinicUser->is_active,
            false,
        );

        $this->assertTrue($decision->allowed);
        $this->assertSame('assigned_contact', $decision->reason);
        $this->assertSame(1, ClinicMembership::query()->where('clinic_id', $clinicId)->where('user_id', $clinicUser->id)->count());
        $this->assertDatabaseHas('clinic_memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinicUser->id,
            'membership_role' => 'contact',
        ]);
        $this->assertSame(0, DB::table('clinic_memberships')->where('user_id', '!=', $clinicUser->id)->count());
    }

    public function test_reviewer_membership_does_not_open_a_clinical_case_or_support_conversation(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $owner = $this->owner();
        $clinicId = $this->clinicId($owner);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician', 'is_active' => true]);
        $representative = User::factory()->create(['role' => 'clinic_rep', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);

        $this->verifyClinician($owner, $clinician, 'verified', null);
        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
        ])->assertRedirect();
        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $representative->id,
            'membership_role' => 'contact',
        ])->assertRedirect();

        $case = PatientCase::query()->create([
            'public_reference' => 'RD-MEM-ISO-1',
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => 'clinician_review',
            'patient_mobile' => '09120000021',
            'patient_mobile_hash' => hash('sha256', 'membership-iso'),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
        ]);

        $this->actingAs($clinician)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
        $this->actingAs($representative)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
        $this->actingAs($owner)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();

        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'assignee_user_id' => $clinician->id,
            'assigned_by_user_id' => $coordinator->id,
            'purpose' => 'clinical_review',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($clinician)->getJson('/api/v1/cases/'.$case->id)->assertOk();

        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Open->value,
            'priority' => 'normal',
            'assignee_user_id' => $coordinator->id,
            'opened_at' => now(),
            'source_language' => 'fa',
        ]);

        $this->actingAs($representative)->getJson('/api/v1/support/'.$conversation->id)->assertNotFound();
        $this->actingAs($clinician)->getJson('/api/v1/support/'.$conversation->id)->assertNotFound();
        $this->actingAs($coordinator)->getJson('/api/v1/support/'.$conversation->id)->assertOk();

        $this->assertFalse(Practitioner::query()->where('user_id', $representative->id)->exists());
    }
}
