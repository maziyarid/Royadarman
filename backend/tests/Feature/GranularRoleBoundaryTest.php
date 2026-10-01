<?php

namespace Tests\Feature;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\Clinic;
use App\Models\ClinicalDocument;
use App\Models\ClinicMembership;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralProposal;
use App\Domain\Support\Enums\ConversationStatus;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GranularRoleBoundaryTest extends TestCase
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

        $this->patient = User::factory()->create([
            'role' => 'patient',
            'phone' => '09120000111',
            'phone_hash' => hash('sha256', 'granular-patient'),
        ]);

        $this->patientCase = PatientCase::query()->create([
            'public_reference' => 'RD-GRAN01',
            'patient_user_id' => $this->patient->id,
            'service_type' => 'opg_review',
            'status' => 'clinician_review',
            'patient_mobile' => '09120000111',
            'patient_mobile_hash' => hash('sha256', 'granular-patient'),
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

    public function test_granular_staff_roles_cannot_view_patient_case_or_download_opg(): void
    {
        foreach ($this->granularStaff() as $staff) {
            $this->actingAs($staff)
                ->getJson('/api/v1/cases/'.$this->patientCase->id)
                ->assertNotFound();

            $this->actingAs($staff)
                ->getJson('/api/v1/cases/'.$this->patientCase->id.'/documents/'.$this->approvedDocument->id.'/content')
                ->assertNotFound();
        }
    }

    public function test_granular_staff_roles_cannot_create_clinical_review(): void
    {
        foreach ($this->granularStaff() as $staff) {
            $this->actingAs($staff)
                ->postJson('/api/v1/staff/cases/'.$this->patientCase->id.'/reviews', [
                    'source_language' => 'fa',
                    'image_adequacy' => 'adequate',
                    'observations' => 'observations',
                    'limitations' => 'limitations',
                    'options' => 'options',
                    'recommended_next_step' => 'next step',
                ])
                ->assertNotFound();
        }
    }

    /** @return list<User> */
    private function granularStaff(): array
    {
        return array_map(
            static fn (string $role): User => User::factory()->create(['role' => $role]),
            ['superadmin', 'developer', 'supervisor', 'receptionist', 'accountant', 'customer_support', 'clinic_manager'],
        );
    }

    public function test_clinic_manager_without_grant_cannot_view_case_even_with_membership(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic G', 'city' => 'Tehran']);
        $manager = User::factory()->create(['role' => 'clinic_manager']);
        ClinicMembership::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $manager->id,
            'membership_role' => 'representative',
            'active_from' => now(),
        ]);

        $this->actingAs($manager)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();
    }

    public function test_customer_support_sees_assigned_support_conversation_but_not_case_or_document(): void
    {
        $support = User::factory()->create(['role' => 'customer_support']);

        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $this->patient->id,
            'case_id' => $this->patientCase->id,
            'status' => ConversationStatus::Open->value,
            'subject' => 'subject',
            'category' => 'general',
            'opened_at' => now(),
        ]);

        $this->actingAs($support)
            ->getJson('/api/v1/support/'.$conversation->id)
            ->assertOk();

        $this->actingAs($support)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertNotFound();

        $this->actingAs($support)
            ->getJson('/api/v1/cases/'.$this->patientCase->id.'/documents/'.$this->approvedDocument->id.'/content')
            ->assertNotFound();
    }

    public function test_customer_support_can_list_support_conversations_but_receptionist_cannot(): void
    {
        SupportConversation::query()->create([
            'patient_user_id' => $this->patient->id,
            'case_id' => $this->patientCase->id,
            'category' => 'general',
            'status' => ConversationStatus::Open->value,
            'subject' => 'list probe',
            'opened_at' => now(),
            'source_language' => 'fa',
        ]);

        $support = User::factory()->create(['role' => 'customer_support']);
        $this->actingAs($support)
            ->getJson('/api/v1/support')
            ->assertOk();

        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $this->actingAs($receptionist)
            ->getJson('/api/v1/support')
            ->assertForbidden();
    }

    public function test_coordination_assign_capability_still_requires_case_view_authorization(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor)
            ->postJson('/api/v1/staff/cases/'.$this->patientCase->id.'/referral-proposals/not-a-proposal/reassign', [
                'clinic_id' => 'irrelevant', 'reason' => 'probe',
            ])
            ->assertNotFound();

        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $this->actingAs($receptionist)
            ->postJson('/api/v1/staff/cases/'.$this->patientCase->id.'/referral-proposals/not-a-proposal/reassign', [
                'clinic_id' => 'irrelevant', 'reason' => 'probe',
            ])
            ->assertNotFound();
    }

    public function test_customer_support_can_open_web_support_workspace_but_receptionist_cannot(): void
    {
        $support = User::factory()->create(['role' => 'customer_support', 'last_authenticated_at' => now()]);
        $this->actingAs($support)
            ->get('/fa/panel/support')
            ->assertOk();

        $receptionist = User::factory()->create(['role' => 'receptionist', 'last_authenticated_at' => now()]);
        $this->actingAs($receptionist)
            ->get('/fa/panel/support')
            ->assertForbidden();
    }

    public function test_receptionist_and_accountant_cannot_answer_or_note_support_conversation(): void
    {
        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $this->patient->id,
            'case_id' => $this->patientCase->id,
            'status' => ConversationStatus::Open->value,
            'subject' => 'subject',
            'category' => 'general',
            'opened_at' => now(),
        ]);

        foreach (['receptionist', 'accountant'] as $role) {
            $staff = User::factory()->create(['role' => $role]);

            $this->actingAs($staff)
                ->postJson('/api/v1/support/'.$conversation->id.'/messages', [
                    'message' => 'hello',
                    'source_language' => 'fa',
                ])
                ->assertNotFound();

            $this->actingAs($staff)
                ->postJson('/api/v1/support/'.$conversation->id.'/internal-notes', [
                    'message' => 'note',
                    'source_language' => 'fa',
                ])
                ->assertForbidden();
        }
    }

    public function test_non_owner_granular_roles_cannot_open_administrators_screen(): void
    {
        foreach (['superadmin', 'developer', 'supervisor', 'receptionist', 'accountant', 'customer_support', 'clinic_manager'] as $role) {
            $staff = User::factory()->create(['role' => $role]);

            $this->actingAs($staff)
                ->get('/fa/panel/administrators')
                ->assertForbidden();
        }
    }

    public function test_dashboard_renders_for_granular_roles_without_unhandled_match(): void
    {
        foreach (['superadmin', 'developer', 'supervisor', 'receptionist', 'accountant', 'customer_support', 'clinic_manager'] as $role) {
            $staff = User::factory()->create([
                'role' => $role,
                'last_authenticated_at' => now(),
            ]);

            $response = $this->actingAs($staff)->get('/fa/dashboard');
            $this->assertSame(200, $response->status(), $role);
        }
    }

    public function test_clinic_manager_with_active_referral_grant_can_view_case(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Clinic GM', 'city' => 'Tehran', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'clinic_manager']);
        ClinicMembership::query()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $manager->id,
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
            'policy_key' => 'referral_sharing',
            'version' => 'granular-1',
            'locale' => 'fa',
            'content' => 'share',
            'content_hash' => hash('sha256', 'share'),
            'published_at' => now(),
        ]);

        $consentId = (string) Str::ulid();
        DB::table('consent_events')->insert([
            'id' => $consentId,
            'subject_user_id' => $this->patient->id,
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

        DB::table('referral_grants')->insert([
            'id' => (string) Str::ulid(),
            'proposal_id' => $proposal->id,
            'case_id' => $this->patientCase->id,
            'clinic_id' => $clinic->id,
            'consent_event_id' => $consentId,
            'scope' => json_encode(['contact', 'service_need']),
            'granted_at' => now(),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)
            ->getJson('/api/v1/cases/'.$this->patientCase->id)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
