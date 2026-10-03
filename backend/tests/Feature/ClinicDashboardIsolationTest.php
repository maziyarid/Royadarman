<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralGrant;
use App\Models\ReferralProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ClinicDashboardIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_start_boundary_and_future_expiry_keep_authorised_projection(): void
    {
        $this->freezeTime();
        $representative = User::factory()->create(['role' => 'clinic_rep']);
        $own = $this->fixture($representative);
        $own['membership']->update(['active_from' => now(), 'active_until' => now()->addMinute()]);

        $this->assertProjection($representative, $own, [], 1);
    }

    public function test_future_expiring_membership_of_another_user_cannot_leak_clinic_or_referral(): void
    {
        $this->freezeTime();
        $representative = User::factory()->create(['role' => 'clinic_rep']);
        $other = User::factory()->create(['role' => 'clinic_rep']);
        $own = $this->fixture($representative);
        $foreign = $this->fixture($other);
        $foreign['membership']->update(['active_until' => now()->addDay()]);

        $this->assertProjection($representative, $own, [$foreign], 1);
    }

    public function test_future_and_expired_memberships_do_not_enter_the_projection(): void
    {
        $this->freezeTime();
        $representative = User::factory()->create(['role' => 'clinic_rep']);
        $own = $this->fixture($representative);
        $future = $this->fixture($representative);
        $future['membership']->update(['active_from' => now()->addMinute()]);
        $expired = $this->fixture($representative);
        $expired['membership']->update(['active_until' => now()]);

        $this->assertProjection($representative, $own, [$future, $expired], 1);
    }

    public function test_inactive_clinic_does_not_expose_referral_or_pending_count(): void
    {
        $representative = User::factory()->create(['role' => 'clinic_rep']);
        $own = $this->fixture($representative);
        $inactive = $this->fixture($representative);
        $inactive['clinic']->update(['is_active' => false]);

        $this->assertProjection($representative, $own, [$inactive], 1);
    }

    public function test_expired_and_revoked_grants_do_not_expose_case_references(): void
    {
        $this->freezeTime();
        $representative = User::factory()->create(['role' => 'clinic_rep']);
        $own = $this->fixture($representative);
        $expired = $this->fixture($representative);
        $expired['grant']->update(['expires_at' => now()]);
        $revoked = $this->fixture($representative);
        $revoked['grant']->update(['revoked_at' => now()]);

        $this->assertProjection($representative, $own, [$expired, $revoked], 3, false);
    }

    public function test_rejected_and_revoked_consent_hide_previously_granted_case_references(): void
    {
        $this->freezeTime();
        $representative = User::factory()->create(['role' => 'clinic_rep']);
        $own = $this->fixture($representative);
        $rejected = $this->fixture($representative);
        $rejected['consent']->update(['decision' => 'rejected']);
        $revoked = $this->fixture($representative);
        $revoked['consent']->update(['revoked_at' => now()]);

        $this->assertProjection($representative, $own, [$rejected, $revoked], 3, false);
    }

    public function test_membership_and_consent_revocation_are_observed_on_next_request(): void
    {
        $this->freezeTime();
        $representative = User::factory()->create(['role' => 'clinic_rep']);
        $own = $this->fixture($representative);
        $changing = $this->fixture($representative);
        $this->actingAs($representative)->getJson('/api/v1/dashboard')->assertJsonCount(2, 'data.active_referral_grants');

        $changing['consent']->update(['revoked_at' => now()]);
        $this->assertProjection($representative, $own, [$changing], 2, false);

        $changing['membership']->update(['active_until' => now()]);
        $this->assertProjection($representative, $own, [$changing], 1);
    }

    private function assertProjection(User $representative, array $own, array $excluded, int $pendingCount, bool $excludeClinics = true): void
    {
        $response = $this->actingAs($representative)->getJson('/api/v1/dashboard')->assertOk();
        $this->assertSame([$own['grant']->id], array_column($response->json('data.active_referral_grants'), 'id'));
        $this->assertSame($pendingCount, $response->json('data.pending_proposals'));
        $clinicIds = array_column($response->json('data.clinics'), 'id');
        $this->assertContains($own['clinic']->id, $clinicIds);
        if ($excludeClinics) {
            $this->assertSame([$own['clinic']->id], $clinicIds);
        }

        $web = $this->actingAs($representative)->get('/en/dashboard')->assertOk()->assertSee($own['case']->public_reference);
        foreach ($excluded as $fixture) {
            $response->assertDontSee($fixture['case']->public_reference);
            $web->assertDontSee($fixture['case']->public_reference);
            if ($excludeClinics) {
                $web->assertDontSee($fixture['clinic']->name);
            }
        }
        $response->assertDontSee('SYNTHETIC-PRIVATE-PATIENT');
        $web->assertDontSee('SYNTHETIC-PRIVATE-PATIENT');
    }

    private function fixture(User $representative): array
    {
        $clinic = Clinic::query()->create(['name' => 'Synthetic Clinic '.Str::ulid(), 'city' => 'Tehran', 'is_active' => true]);
        $membership = ClinicMembership::query()->create([
            'clinic_id' => $clinic->id, 'user_id' => $representative->id, 'membership_role' => 'contact',
            'active_from' => now()->subDay(), 'active_until' => null,
        ]);
        $patient = User::factory()->create(['role' => 'patient']);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)), 'patient_user_id' => $patient->id,
            'patient_name' => 'SYNTHETIC-PRIVATE-PATIENT', 'service_type' => 'opg_review', 'status' => 'submitted',
            'patient_mobile' => '09120000001', 'patient_mobile_hash' => hash('sha256', Str::ulid()),
            'budget_band' => 'balanced', 'source_language' => 'fa',
        ]);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $representative->id,
            'status' => 'proposed', 'reasoning' => 'Synthetic logistics only', 'source_language' => 'fa', 'proposed_at' => now(),
        ]);
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing', 'version' => (string) Str::ulid(), 'locale' => 'fa',
            'content' => 'Synthetic sharing policy', 'content_hash' => hash('sha256', 'Synthetic sharing policy'), 'published_at' => now(),
        ]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $patient->id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'referral_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'synthetic-ip'), 'user_agent_hash' => hash('sha256', 'synthetic-agent'), 'created_at' => now(),
        ]);
        $grant = ReferralGrant::query()->create([
            'proposal_id' => $proposal->id, 'case_id' => $case->id, 'clinic_id' => $clinic->id,
            'consent_event_id' => $consent->id, 'scope' => ['basic'], 'granted_at' => now(), 'expires_at' => now()->addDay(),
        ]);

        return compact('clinic', 'membership', 'case', 'proposal', 'consent', 'grant');
    }
}
