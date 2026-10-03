<?php

namespace Tests\Feature;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\Clinic;
use App\Models\ClinicalDocument;
use App\Models\ClinicMembership;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReferralGrantLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private PatientCase $patientCase;

    private Clinic $clinicA;

    private Clinic $clinicB;

    private string $grantId;

    private string $consentId;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('royadarman.intake_enabled', true);
        Storage::fake('private-opg');

        $patient = User::factory()->create([
            'role' => 'patient',
            'phone' => '09120000222',
            'phone_hash' => hash('sha256', 'lifecycle-patient'),
        ]);

        $this->patientCase = PatientCase::query()->create([
            'public_reference' => 'RD-LIFE01',
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => 'clinician_review',
            'patient_mobile' => '09120000222',
            'patient_mobile_hash' => hash('sha256', 'lifecycle-patient'),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
        ]);

        $document = ClinicalDocument::query()->create([
            'case_id' => $this->patientCase->id,
            'uploaded_by_user_id' => $patient->id,
            'storage_disk' => 'private-opg',
            'storage_key' => 'opg/'.$this->patientCase->id.'/'.Str::ulid().'.png',
            'original_name' => encrypt('scan.png'),
            'detected_mime' => 'image/png',
            'byte_size' => 1024,
            'sha256' => hash('sha256', 'content'),
            'status' => DocumentStatus::Approved,
            'approved_at' => now(),
        ]);
        Storage::disk('private-opg')->put($document->storage_key, 'content');

        $this->clinicA = Clinic::query()->create(['name' => 'Clinic Alpha', 'city' => 'Tehran', 'is_active' => true]);
        $this->clinicB = Clinic::query()->create(['name' => 'Clinic Beta', 'city' => 'Tehran', 'is_active' => true]);

        $policy = PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing',
            'version' => 'lifecycle-1',
            'locale' => 'fa',
            'content' => 'share',
            'content_hash' => hash('sha256', 'share'),
            'published_at' => now(),
        ]);

        $this->consentId = (string) Str::ulid();
        DB::table('consent_events')->insert([
            'id' => $this->consentId,
            'subject_user_id' => $patient->id,
            'case_id' => $this->patientCase->id,
            'policy_version_id' => $policy->id,
            'purpose' => 'referral_sharing',
            'decision' => 'accepted',
            'locale' => 'fa',
            'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'),
            'user_agent_hash' => hash('sha256', 'ua'),
            'created_at' => now(),
        ]);

        $proposal = ReferralProposal::query()->create([
            'case_id' => $this->patientCase->id,
            'clinic_id' => $this->clinicA->id,
            'proposed_by_user_id' => $patient->id,
            'status' => 'accepted',
            'reasoning' => 'reason',
            'source_language' => 'fa',
            'proposed_at' => now(),
            'decided_at' => now(),
        ]);

        $this->grantId = (string) Str::ulid();
        DB::table('referral_grants')->insert([
            'id' => $this->grantId,
            'proposal_id' => $proposal->id,
            'case_id' => $this->patientCase->id,
            'clinic_id' => $this->clinicA->id,
            'consent_event_id' => $this->consentId,
            'scope' => json_encode(['contact', 'service_need']),
            'granted_at' => now(),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function memberOf(Clinic $clinic, string $role = 'clinic_rep'): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        ClinicMembership::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'membership_role' => 'representative',
            'active_from' => now()->subDay(),
            'active_until' => null,
        ]);

        return $user;
    }

    public function test_clinic_a_member_with_grant_views_case_and_opg(): void
    {
        $rep = $this->memberOf($this->clinicA);

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_clinic_b_member_is_isolated_from_clinic_a_grant(): void
    {
        $repB = $this->memberOf($this->clinicB);

        $this->actingAs($repB)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();
    }

    public function test_dual_membership_user_is_isolated_without_grant_in_second_clinic(): void
    {
        $dual = $this->memberOf($this->clinicA);
        ClinicMembership::query()->create([
            'clinic_id' => $this->clinicB->id,
            'user_id' => $dual->id,
            'membership_role' => 'representative',
            'active_from' => now()->subDay(),
            'active_until' => null,
        ]);

        $this->actingAs($dual)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertOk();

        DB::table('referral_grants')->where('id', $this->grantId)->update(['revoked_at' => now()]);

        $this->actingAs($dual)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();
    }

    public function test_grant_expiry_denies_case_access(): void
    {
        $rep = $this->memberOf($this->clinicA);

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertOk();

        DB::table('referral_grants')->where('id', $this->grantId)->update(['expires_at' => now()->subSecond()]);

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();
    }

    public function test_consent_revocation_denies_case_access_even_with_active_grant(): void
    {
        $rep = $this->memberOf($this->clinicA);

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertOk();

        DB::table('consent_events')->where('id', $this->consentId)->update(['revoked_at' => now()]);

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();
    }

    public function test_membership_expiry_denies_case_access_even_with_active_grant(): void
    {
        $rep = $this->memberOf($this->clinicA);
        ClinicMembership::query()
            ->where('user_id', $rep->id)
            ->where('clinic_id', $this->clinicA->id)
            ->update(['active_until' => now()->subSecond()]);

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();
    }

    public function test_inactive_user_is_denied_case_access_even_with_active_grant(): void
    {
        $rep = $this->memberOf($this->clinicA);
        $rep->forceFill(['is_active' => false])->save();

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertForbidden();
    }
}
