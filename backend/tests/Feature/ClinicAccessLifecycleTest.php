<?php

namespace Tests\Feature;

use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicAccessLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_access_requires_active_clinic_and_active_membership(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $representative = User::factory()->create(['role' => 'clinic_rep', 'is_active' => true]);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'guidance_referral',
            'status' => 'referred',
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 2,
        ]);

        $clinicId = (string) Str::ulid();
        DB::table('clinics')->insert([
            'id' => $clinicId, 'name' => 'Clinic', 'city' => 'Tehran', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $membershipId = (string) Str::ulid();
        DB::table('clinic_memberships')->insert([
            'id' => $membershipId, 'clinic_id' => $clinicId, 'user_id' => $representative->id,
            'membership_role' => 'contact', 'active_from' => now()->subDay(), 'active_until' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $policy = PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing', 'version' => 'v1', 'locale' => 'fa',
            'content' => 'share', 'content_hash' => hash('sha256', 'share'), 'published_at' => now(),
        ]);
        $consentId = (string) Str::ulid();
        DB::table('consent_events')->insert([
            'id' => $consentId,
            'subject_user_id' => $patient->id,
            'case_id' => $case->id,
            'policy_version_id' => $policy->id,
            'purpose' => 'referral_sharing',
            'decision' => 'accepted',
            'locale' => 'fa',
            'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'),
            'user_agent_hash' => hash('sha256', 'ua'),
            'created_at' => now(),
        ]);
        $proposalId = (string) Str::ulid();
        DB::table('referral_proposals')->insert([
            'id' => $proposalId, 'case_id' => $case->id, 'clinic_id' => $clinicId,
            'proposed_by_user_id' => $representative->id, 'status' => 'accepted',
            'reasoning' => 'test', 'source_language' => 'fa', 'proposed_at' => now(),
            'decided_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('referral_grants')->insert([
            'id' => (string) Str::ulid(), 'proposal_id' => $proposalId, 'consent_event_id' => $consentId,
            'case_id' => $case->id, 'clinic_id' => $clinicId,
            'scope' => json_encode(['contact', 'service_need'], JSON_THROW_ON_ERROR),
            'granted_at' => now(), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue($representative->can('view', $case));

        DB::table('clinics')->where('id', $clinicId)->update(['is_active' => false]);
        $this->assertFalse($representative->can('view', $case));

        DB::table('clinics')->where('id', $clinicId)->update(['is_active' => true]);
        DB::table('clinic_memberships')->where('id', $membershipId)->update(['active_until' => now()->subSecond()]);
        $this->assertFalse($representative->can('view', $case));
    }

    public function test_patient_cannot_accept_stale_proposal_after_clinic_is_deactivated(): void
    {
        config()->set('royadarman.intake_enabled', true);
        config()->set('royadarman.referral.grant_ttl_minutes', 1440);

        $patient = User::factory()->create(['role' => 'patient', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'guidance_referral',
            'status' => 'in_coordination',
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 2,
        ]);

        $clinicId = (string) Str::ulid();
        DB::table('clinics')->insert([
            'id' => $clinicId, 'name' => 'Inactive Clinic', 'city' => 'Tehran', 'is_active' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $proposalId = (string) Str::ulid();
        DB::table('referral_proposals')->insert([
            'id' => $proposalId,
            'case_id' => $case->id,
            'clinic_id' => $clinicId,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => 'Previously active provider',
            'source_language' => 'fa',
            'proposed_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $content = 'share referral details';
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing',
            'version' => 'v-stale-clinic',
            'locale' => 'fa',
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'published_at' => now(),
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/referrals/{$proposalId}/decision", [
                'decision' => 'accepted',
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'referral.not_available');

        $this->assertDatabaseHas('referral_proposals', ['id' => $proposalId, 'status' => 'proposed']);
        $this->assertDatabaseMissing('consent_events', ['case_id' => $case->id, 'purpose' => 'referral_sharing']);
        $this->assertDatabaseMissing('referral_grants', ['proposal_id' => $proposalId]);
    }
}
