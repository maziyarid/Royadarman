<?php

namespace Tests\Feature;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\Clinic;
use App\Models\ClinicalDocument;
use App\Models\ClinicMembership;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\Practitioner;
use App\Models\ReferralProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private PatientCase $patientCase;

    private ClinicalDocument $approvedDocument;

    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', true);
        Storage::fake('private-opg');

        $this->patient = User::factory()->create(['role' => 'patient', 'phone' => '09120000001', 'phone_hash' => hash('sha256', 'patient-1')]);
        $this->patientCase = PatientCase::query()->create([
            'public_reference' => 'RD-AUTH01',
            'patient_user_id' => $this->patient->id,
            'service_type' => 'opg_review',
            'status' => 'clinician_review',
            'patient_mobile' => '09120000001',
            'patient_mobile_hash' => hash('sha256', 'patient-1'),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
        ]);

        $this->approvedDocument = ClinicalDocument::query()->create([
            'case_id' => $this->patientCase->id,
            'uploaded_by_user_id' => $this->patient->id,
            'storage_disk' => 'private-opg',
            'storage_key' => 'opg/'.$this->patientCase->id.'/'.Str::ulid().'.png',
            'original_name' => encrypt('scan.png'),
            'detected_mime' => 'image/png',
            'byte_size' => 1024,
            'sha256' => hash('sha256', 'content'),
            'status' => DocumentStatus::Approved,
            'approved_at' => now(),
        ]);
        Storage::disk('private-opg')->put($this->approvedDocument->storage_key, 'content');
    }

    public function test_owner_cannot_view_another_patients_case_or_download_opg(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();

        $this->actingAs($owner)
            ->getJson('/api/v1/cases/'.$this->patientCase->id.'/documents/'.$this->approvedDocument->id.'/content')
            ->assertNotFound();
    }

    public function test_tech_admin_cannot_view_patients_case_or_download_opg(): void
    {
        $techAdmin = User::factory()->create(['role' => 'tech_admin']);

        $this->actingAs($techAdmin)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();

        $this->actingAs($techAdmin)
            ->getJson('/api/v1/cases/'.$this->patientCase->id.'/documents/'.$this->approvedDocument->id.'/content')
            ->assertNotFound();
    }

    public function test_clinic_rep_without_grant_cannot_view_case_or_download_opg(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic A', 'city' => 'Tehran']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        ClinicMembership::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $rep->id,
            'membership_role' => 'representative',
            'active_from' => now(),
        ]);

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id.'/documents/'.$this->approvedDocument->id.'/content')
            ->assertNotFound();
    }

    public function test_clinician_without_assignment_cannot_view_case_or_download_opg(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);
        Practitioner::query()->create([
            'user_id' => $clinician->id,
            'licence_number' => encrypt('LIC-FREE'),
            'licence_hash' => hash('sha256', 'LIC-FREE'),
            'credential_status' => 'verified',
            'verified_at' => now()->subDay(),
        ]);

        $this->actingAs($clinician)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();

        $this->actingAs($clinician)
            ->getJson('/api/v1/cases/'.$this->patientCase->id.'/documents/'.$this->approvedDocument->id.'/content')
            ->assertNotFound();
    }

    public function test_clinician_with_unverified_credential_cannot_view_case_or_download_opg(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);
        Practitioner::query()->create([
            'user_id' => $clinician->id,
            'licence_number' => encrypt('LIC-PENDING'),
            'licence_hash' => hash('sha256', 'LIC-PENDING'),
            'credential_status' => 'pending',
        ]);
        $this->assignClinicalReview($this->patientCase, $clinician);

        $this->actingAs($clinician)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();

        $this->actingAs($clinician)
            ->getJson('/api/v1/cases/'.$this->patientCase->id.'/documents/'.$this->approvedDocument->id.'/content')
            ->assertNotFound();
    }

    public function test_coordinator_without_assignment_cannot_view_case(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);

        $this->actingAs($coordinator)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();
    }

    public function test_patient_cannot_view_another_patients_case_or_download_opg(): void
    {
        $otherPatient = User::factory()->create(['role' => 'patient', 'phone' => '09120000099', 'phone_hash' => hash('sha256', 'other-patient')]);

        $this->actingAs($otherPatient)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();

        $this->actingAs($otherPatient)
            ->getJson('/api/v1/cases/'.$this->patientCase->id.'/documents/'.$this->approvedDocument->id.'/content')
            ->assertNotFound();
    }

    public function test_owner_cannot_create_review(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)
            ->postJson('/api/v1/staff/cases/'.$this->patientCase->id.'/reviews', $this->reviewPayload())
            ->assertNotFound();
    }

    public function test_coordinator_cannot_create_review(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($coordinator)
            ->postJson('/api/v1/staff/cases/'.$this->patientCase->id.'/reviews', $this->reviewPayload())
            ->assertNotFound();
    }

    public function test_clinic_rep_with_active_grant_can_view_case(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic Granted', 'city' => 'Tehran', 'is_active' => true]);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        ClinicMembership::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $rep->id,
            'membership_role' => 'representative',
            'active_from' => now(),
        ]);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $this->patientCase->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $this->patient->id,
            'status' => 'accepted',
            'reasoning' => 'reason',
            'source_language' => 'fa',
            'proposed_at' => now(),
            'decided_at' => now(),
        ]);
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing', 'version' => 'auth-matrix-1', 'locale' => 'fa',
            'content' => 'share', 'content_hash' => hash('sha256', 'share'), 'published_at' => now(),
        ]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $this->patient->id, 'case_id' => $this->patientCase->id,
            'policy_version_id' => $policy->id, 'purpose' => 'referral_sharing',
            'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'), 'user_agent_hash' => hash('sha256', 'ua'), 'created_at' => now(),
        ]);
        DB::table('referral_grants')->insert([
            'id' => (string) Str::ulid(),
            'proposal_id' => $proposal->id,
            'case_id' => $this->patientCase->id,
            'clinic_id' => $clinic->id,
            'consent_event_id' => $consent->id,
            'scope' => json_encode(['contact', 'service_need']),
            'granted_at' => now(),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($rep)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_clinician_with_active_assignment_and_verified_credential_can_view_case(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);
        Practitioner::query()->create([
            'user_id' => $clinician->id,
            'licence_number' => encrypt('LIC-OK'),
            'licence_hash' => hash('sha256', 'LIC-OK'),
            'credential_status' => 'verified',
            'verified_at' => now()->subDay(),
        ]);
        $this->assignClinicalReview($this->patientCase, $clinician);

        $this->actingAs($clinician)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertOk();
    }

    public function test_coordinator_with_active_assignment_can_view_case(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $this->patientCase->id,
            'assignee_user_id' => $coordinator->id,
            'assigned_by_user_id' => $coordinator->id,
            'purpose' => 'coordination',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertOk();
    }

    public function test_unauthenticated_request_to_case_is_unauthorized(): void
    {
        $this->getJson('/api/v1/cases/'.$this->patientCase->id)->assertUnauthorized();
    }

    public function test_intake_kill_switch_blocks_new_case_acquisition_only(): void
    {
        config()->set('royadarman.intake_enabled', false);

        $this->actingAs($this->patient)
            ->postJson('/api/v1/cases/draft', [
                'service_type' => 'guidance_referral', 'budget_band' => 'call',
                'budget_input_unit' => 'toman', 'source_language' => 'fa',
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'intake_not_enabled');

        $this->actingAs($this->patient)
            ->postJson('/api/v1/cases/'.$this->patientCase->id.'/submit', [
                'version' => 1, 'policy_version' => 'v1', 'content_hash' => str_repeat('0', 64),
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'intake_not_enabled');
    }

    private function assignClinicalReview(PatientCase $case, User $clinician): void
    {
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'assignee_user_id' => $clinician->id,
            'assigned_by_user_id' => $clinician->id,
            'purpose' => 'clinical_review',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reviewPayload(): array
    {
        return [
            'source_language' => 'fa',
            'image_adequacy' => 'adequate',
            'observations' => 'observations',
            'limitations' => 'limitations',
            'options' => 'options',
            'recommended_next_step' => 'next step',
        ];
    }
}
