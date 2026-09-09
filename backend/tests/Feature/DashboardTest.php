<?php

namespace Tests\Feature;

use App\Domain\Identity\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\OutboxEvent;
use App\Models\PatientCase;
use App\Models\Practitioner;
use App\Models\ReferralGrant;
use App\Models\ReferralProposal;
use App\Models\ReviewRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeCase(User $patient, array $overrides = []): PatientCase
    {
        return PatientCase::query()->create(array_merge([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => 'submitted',
            'patient_mobile' => '09120000001',
            'patient_mobile_hash' => hash('sha256', Str::random(8)),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
        ], $overrides));
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_patient_dashboard_only_lists_own_cases(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'phone_hash' => hash('sha256', 'p1')]);
        $other = User::factory()->create(['role' => 'patient', 'phone_hash' => hash('sha256', 'p2')]);

        $own = $this->makeCase($patient);
        $this->makeCase($other, ['public_reference' => 'RD-OTHER1']);

        $resp = $this->actingAs($patient)->getJson('/api/v1/dashboard')->assertOk();

        $caseIds = collect($resp->json('data.cases'))->pluck('id');
        $this->assertContains($own->id, $caseIds);
        $this->assertNotContains('RD-OTHER1', collect($resp->json('data.cases'))->pluck('id'));
        $this->assertSame('patient', $resp->json('data.role'));
    }

    public function test_clinician_dashboard_shows_assigned_reviews_and_credential_state(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);
        $patient = User::factory()->create(['role' => 'patient', 'phone_hash' => hash('sha256', 'pc')]);
        $case = $this->makeCase($patient);

        Practitioner::query()->create([
            'user_id' => $clinician->id,
            'licence_number' => 'LIC-12345',
            'licence_hash' => hash('sha256', 'LIC-12345'),
            'credential_status' => 'verified',
            'verified_at' => now(),
        ]);

        ReviewRevision::query()->create([
            'case_id' => $case->id,
            'clinician_user_id' => $clinician->id,
            'revision_number' => 1,
            'source_language' => 'fa',
            'image_adequacy' => 'adequate',
            'observations' => 'obs',
            'limitations' => 'none',
            'options' => 'opt',
            'recommended_next_step' => 'visit',
        ]);

        $resp = $this->actingAs($clinician)->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame('clinician', $resp->json('data.role'));
        $this->assertTrue($resp->json('data.credential_valid'));
        $this->assertTrue($resp->json('data.can_publish'));
        $this->assertSame(1, count($resp->json('data.assigned_reviews')));
        $this->assertSame(1, $resp->json('data.open_drafts_count'));
    }

    public function test_clinician_without_verified_credential_cannot_publish(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);
        Practitioner::query()->create([
            'user_id' => $clinician->id,
            'licence_number' => 'LIC-67890',
            'licence_hash' => hash('sha256', 'LIC-67890'),
            'credential_status' => 'pending',
        ]);

        $resp = $this->actingAs($clinician)->getJson('/api/v1/dashboard')->assertOk();

        $this->assertFalse($resp->json('data.credential_valid'));
        $this->assertFalse($resp->json('data.can_publish'));
    }

    public function test_clinic_dashboard_shows_only_grants_for_owned_clinics(): void
    {
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinic = Clinic::query()->create(['name' => 'Test Clinic', 'city' => 'Tehran', 'is_active' => true]);
        Clinic::query()->create(['name' => 'Other', 'city' => 'Tehran', 'is_active' => true]);

        ClinicMembership::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $rep->id,
            'membership_role' => 'representative',
            'active_from' => now(),
        ]);

        $patient = User::factory()->create(['role' => 'patient', 'phone_hash' => hash('sha256', 'p3')]);
        $case = $this->makeCase($patient);

        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $rep->id,
            'status' => 'proposed',
            'reasoning' => 'test',
            'source_language' => 'fa',
            'proposed_at' => now(),
        ]);

        ReferralGrant::query()->create([
            'proposal_id' => $proposal->id,
            'case_id' => $case->id,
            'clinic_id' => $clinic->id,
            'scope' => ['basic'],
            'granted_at' => now(),
        ]);

        $resp = $this->actingAs($rep)->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame('clinic_rep', $resp->json('data.role'));
        $this->assertSame(1, count($resp->json('data.active_referral_grants')));
        $this->assertSame(1, count($resp->json('data.clinics')));
        $this->assertSame($clinic->id, $resp->json('data.clinics.0.id'));
    }

    public function test_coordinator_dashboard_shows_assigned_queue_and_awaiting_count(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient', 'phone_hash' => hash('sha256', 'pc2')]);
        $this->makeCase($patient, ['current_coordinator_id' => $coordinator->id, 'status' => 'awaiting_patient']);

        $resp = $this->actingAs($coordinator)->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame('coordinator', $resp->json('data.role'));
        $this->assertSame(1, $resp->json('data.awaiting_patient_count'));
        $this->assertSame(1, count($resp->json('data.case_queue')));
    }

    public function test_business_admin_dashboard_shows_aggregate_metrics_without_clinical_detail(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $patient = User::factory()->create(['role' => 'patient', 'phone_hash' => hash('sha256', 'pa')]);
        $this->makeCase($patient);
        Clinic::query()->create(['name' => 'C1', 'city' => 'Tehran', 'is_active' => true]);

        $resp = $this->actingAs($owner)->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame('owner', $resp->json('data.role'));
        $this->assertSame(1, $resp->json('data.total_cases'));
        $this->assertSame(1, $resp->json('data.total_clinics'));
        $this->assertSame(1, $resp->json('data.active_clinics'));
        $this->assertArrayNotHasKey('cases', $resp->json('data'));
    }

    public function test_technical_admin_dashboard_shows_ops_health_no_clinical_data(): void
    {
        $tech = User::factory()->create(['role' => 'tech_admin']);
        OutboxEvent::query()->create([
            'event_type' => 'notification',
            'aggregate_type' => 'patient_case',
            'aggregate_id' => (string) Str::ulid(),
            'payload' => ['x' => 1],
            'deduplication_key' => 'dk-'.Str::uuid(),
            'available_at' => now(),
        ]);
        AuditEvent::query()->create([
            'actor_user_id' => $tech->id,
            'action' => 'test.event',
            'resource_type' => 'system',
            'resource_id' => '1',
            'result' => 'success',
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);

        $resp = $this->actingAs($tech)->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame('tech_admin', $resp->json('data.role'));
        $this->assertSame(1, $resp->json('data.outbox.pending'));
        $this->assertArrayNotHasKey('cases', $resp->json('data'));
        $this->assertArrayNotHasKey('case_queue', $resp->json('data'));
    }

    public function test_every_role_returns_a_dashboard_payload(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role->value]);
            $resp = $this->actingAs($user)->getJson('/api/v1/dashboard');
            $this->assertSame(200, $resp->status(), "Role {$role->value} failed");
            $this->assertSame($role->value, $resp->json('data.role'), "Role mismatch for {$role->value}");
        }
    }
}
