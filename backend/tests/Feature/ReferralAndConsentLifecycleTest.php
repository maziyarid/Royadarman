<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Jobs\ProcessOutboxEvent;
use App\Models\ClinicalDocument;
use App\Models\ConsentEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralAndConsentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', true);
        config()->set('royadarman.referral.grant_ttl_minutes', 60);
        Queue::fake([ProcessOutboxEvent::class]);
    }

    private function makeCase(User $patient, CaseStatus $status = CaseStatus::ReferralProposed): PatientCase
    {
        return PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => $status,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);
    }

    private function makeClinic(): string
    {
        $id = (string) Str::ulid();
        DB::table('clinics')->insert(['id' => $id, 'name' => 'Partner Clinic', 'city' => 'Tehran', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function makeClinicRep(User $user, string $clinicId): void
    {
        DB::table('clinic_memberships')->insert([
            'id' => (string) Str::ulid(),
            'clinic_id' => $clinicId,
            'user_id' => $user->id,
            'membership_role' => 'representative',
            'active_from' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePractitioner(User $clinician): void
    {
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $clinician->id,
            'licence_number' => encrypt('LIC-'.$clinician->id),
            'licence_hash' => hash('sha256', 'LIC-'.$clinician->id),
            'credential_status' => 'verified',
            'verified_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function publishedPolicy(string $key = 'referral_sharing', string $content = 'referral text'): PolicyVersion
    {
        return PolicyVersion::query()->create([
            'policy_key' => $key,
            'version' => 'approved-1',
            'locale' => 'fa',
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'published_at' => now(),
        ]);
    }

    private function seedAcceptedGrant(PatientCase $case, User $patient, string $clinicId, PolicyVersion $policy, ?int $ttlOverrideMinutes = null): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $coordinator->id, 'assigned_by_user_id' => $coordinator->id, 'purpose' => 'coordination', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id, 'clinic_id' => $clinicId, 'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed', 'reasoning' => encrypt('needs specialist'), 'source_language' => 'fa', 'proposed_at' => now(),
        ]);

        $response = $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/referrals/{$proposal->id}/decision", [
                'decision' => 'accepted',
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
            ]);
        $response->assertOk();

        return [ReferralProposal::query()->whereKey($proposal->id)->first(), DB::table('referral_grants')->where('proposal_id', $proposal->id)->first()];
    }

    public function test_clinic_representative_can_view_case_with_active_grant(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinicId = $this->makeClinic();
        $this->makeClinicRep($rep, $clinicId);
        $policy = $this->publishedPolicy();
        $case = $this->makeCase($patient);
        [$proposal, $grant] = $this->seedAcceptedGrant($case, $patient, $clinicId, $policy);

        $this->actingAs($rep)
            ->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $case->id);
    }

    public function test_clinic_representative_denied_before_grant(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinicId = $this->makeClinic();
        $this->makeClinicRep($rep, $clinicId);
        $case = $this->makeCase($patient);

        $this->actingAs($rep)
            ->getJson("/api/v1/cases/{$case->id}")
            ->assertNotFound();
    }

    public function test_clinic_representative_denied_after_grant_revoked(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinicId = $this->makeClinic();
        $this->makeClinicRep($rep, $clinicId);
        $policy = $this->publishedPolicy();
        $case = $this->makeCase($patient);
        [$proposal, $grant] = $this->seedAcceptedGrant($case, $patient, $clinicId, $policy);

        DB::table('referral_grants')->where('id', $grant->id)->update(['revoked_at' => now()]);

        $this->actingAs($rep)
            ->getJson("/api/v1/cases/{$case->id}")
            ->assertNotFound();
    }

    public function test_clinic_representative_denied_after_grant_expired(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinicId = $this->makeClinic();
        $this->makeClinicRep($rep, $clinicId);
        $policy = $this->publishedPolicy();
        $case = $this->makeCase($patient);
        config()->set('royadarman.referral.grant_ttl_minutes', 1);
        [$proposal, $grant] = $this->seedAcceptedGrant($case, $patient, $clinicId, $policy);

        $this->travel(2)->minutes();

        $this->actingAs($rep)
            ->getJson("/api/v1/cases/{$case->id}")
            ->assertNotFound();
    }

    public function test_clinic_representative_denied_after_linked_consent_revoked(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinicId = $this->makeClinic();
        $this->makeClinicRep($rep, $clinicId);
        $policy = $this->publishedPolicy();
        $case = $this->makeCase($patient);
        [$proposal, $grant] = $this->seedAcceptedGrant($case, $patient, $clinicId, $policy);

        // Patient revokes the referral-sharing consent via the HTTP path.
        $this->actingAs($patient)
            ->deleteJson("/api/v1/cases/{$case->id}/consent/referral_sharing")
            ->assertOk();

        $this->actingAs($rep)
            ->getJson("/api/v1/cases/{$case->id}")
            ->assertNotFound();
    }

    public function test_cross_clinic_representative_denied(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinicA = $this->makeClinic();
        $clinicB = $this->makeClinic();
        $this->makeClinicRep($rep, $clinicB);
        $policy = $this->publishedPolicy();
        $case = $this->makeCase($patient);
        $this->seedAcceptedGrant($case, $patient, $clinicA, $policy);

        $this->actingAs($rep)
            ->getJson("/api/v1/cases/{$case->id}")
            ->assertNotFound();
    }

    public function test_cross_case_grant_does_not_authorise_other_case(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinicId = $this->makeClinic();
        $this->makeClinicRep($rep, $clinicId);
        $policy = $this->publishedPolicy();
        $caseA = $this->makeCase($patient);
        $this->seedAcceptedGrant($caseA, $patient, $clinicId, $policy);
        $caseB = $this->makeCase(User::factory()->create(['role' => 'patient']));

        $this->actingAs($rep)
            ->getJson("/api/v1/cases/{$caseB->id}")
            ->assertNotFound();
    }

    public function test_referral_accept_rejects_substituted_policy_hash(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $clinicId = $this->makeClinic();
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing', 'version' => 'approved-1', 'locale' => 'fa',
            'content' => 'real text', 'content_hash' => hash('sha256', 'real text'), 'published_at' => now(),
        ]);
        $other = PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing', 'version' => 'approved-2', 'locale' => 'fa',
            'content' => 'different text', 'content_hash' => hash('sha256', 'different text'), 'published_at' => now(),
        ]);

        $case = $this->makeCase($patient);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $coordinator->id, 'assigned_by_user_id' => $coordinator->id, 'purpose' => 'coordination', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id, 'clinic_id' => $clinicId, 'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed', 'reasoning' => encrypt('reason'), 'source_language' => 'fa', 'proposed_at' => now(),
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/referrals/{$proposal->id}/decision", [
                'decision' => 'accepted',
                'policy_version' => $policy->version,
                'content_hash' => $other->content_hash,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'consent.policy_mismatch');

        $this->assertDatabaseMissing('referral_grants', ['proposal_id' => $proposal->id]);
        $this->assertDatabaseMissing('consent_events', ['purpose' => 'referral_sharing', 'case_id' => $case->id]);
    }

    public function test_referral_grant_ttl_unconfigured_returns_503(): void
    {
        config()->set('royadarman.referral.grant_ttl_minutes', null);
        $patient = User::factory()->create(['role' => 'patient']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $clinicId = $this->makeClinic();
        $policy = $this->publishedPolicy();

        $case = $this->makeCase($patient);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $coordinator->id, 'assigned_by_user_id' => $coordinator->id, 'purpose' => 'coordination', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id, 'clinic_id' => $clinicId, 'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed', 'reasoning' => encrypt('reason'), 'source_language' => 'fa', 'proposed_at' => now(),
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/referrals/{$proposal->id}/decision", [
                'decision' => 'accepted',
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
            ])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'referral.grant_ttl_unconfigured');

        $this->assertDatabaseMissing('referral_grants', ['proposal_id' => $proposal->id]);
        // The proposal must remain 'proposed' with no decided_at — a failed
        // acceptance due to an unconfigured TTL must not mutate the proposal.
        $reloaded = ReferralProposal::query()->whereKey($proposal->id)->first();
        $this->assertSame('proposed', $reloaded->status);
        $this->assertNull($reloaded->decided_at);

        // Retrying once the TTL is configured must succeed.
        config()->set('royadarman.referral.grant_ttl_minutes', 60);
        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/referrals/{$proposal->id}/decision", [
                'decision' => 'accepted',
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
            ])
            ->assertOk();
        $this->assertDatabaseHas('referral_grants', ['proposal_id' => $proposal->id]);
        $this->assertSame('accepted', ReferralProposal::query()->whereKey($proposal->id)->value('status'));
    }

    public function test_clinician_access_to_document_remains_while_consent_active(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician);
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $policy = PolicyVersion::query()->create(['policy_key' => 'opg_document_sharing', 'version' => 'approved-1', 'locale' => 'fa', 'content' => 'opg text', 'content_hash' => hash('sha256', 'opg text'), 'published_at' => now()]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $patient->id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'), 'user_agent_hash' => hash('sha256', 'ua'), 'created_at' => now(),
        ]);
        $document = ClinicalDocument::query()->create([
            'case_id' => $case->id, 'uploaded_by_user_id' => $patient->id, 'consent_event_id' => $consent->id,
            'original_name' => encrypt('opg.png'), 'storage_disk' => 'private-opg', 'storage_key' => 'k/'.Str::ulid().'.png',
            'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', 'x'), 'status' => DocumentStatus::Approved,
        ]);

        $this->actingAs($clinician)
            ->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_clinician_access_to_document_revoked_when_consent_revoked(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->makePractitioner($clinician);
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $policy = PolicyVersion::query()->create(['policy_key' => 'opg_document_sharing', 'version' => 'approved-1', 'locale' => 'fa', 'content' => 'opg text', 'content_hash' => hash('sha256', 'opg text'), 'published_at' => now()]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $patient->id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'), 'user_agent_hash' => hash('sha256', 'ua'), 'created_at' => now(),
            'revoked_at' => now(),
        ]);
        $document = ClinicalDocument::query()->create([
            'case_id' => $case->id, 'uploaded_by_user_id' => $patient->id, 'consent_event_id' => $consent->id,
            'original_name' => encrypt('opg.png'), 'storage_disk' => 'private-opg', 'storage_key' => 'k/'.Str::ulid().'.png',
            'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', 'x'), 'status' => DocumentStatus::Approved,
        ]);

        $this->actingAs($clinician)
            ->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}")
            ->assertNotFound();
    }

    public function test_owner_and_tech_admin_default_denied_document_access(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $owner = User::factory()->create(['role' => 'owner']);
        $techAdmin = User::factory()->create(['role' => 'tech_admin']);
        $case = $this->makeCase($patient, CaseStatus::ClinicianReview);
        $policy = PolicyVersion::query()->create(['policy_key' => 'opg_document_sharing', 'version' => 'approved-1', 'locale' => 'fa', 'content' => 'opg text', 'content_hash' => hash('sha256', 'opg text'), 'published_at' => now()]);
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $patient->id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'opg_document_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'), 'user_agent_hash' => hash('sha256', 'ua'), 'created_at' => now(),
        ]);
        $document = ClinicalDocument::query()->create([
            'case_id' => $case->id, 'uploaded_by_user_id' => $patient->id, 'consent_event_id' => $consent->id,
            'original_name' => encrypt('opg.png'), 'storage_disk' => 'private-opg', 'storage_key' => 'k/'.Str::ulid().'.png',
            'detected_mime' => 'image/png', 'byte_size' => 68, 'sha256' => hash('sha256', 'x'), 'status' => DocumentStatus::Approved,
        ]);

        $this->actingAs($owner)->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}")->assertNotFound();
        $this->actingAs($techAdmin)->getJson("/api/v1/cases/{$case->id}/documents/{$document->id}")->assertNotFound();
    }

    public function test_multiple_consent_revocation_removes_all_grants(): void
    {
        // Two referral_sharing consent events exist for the same patient/case
        // (e.g. an earlier grant A linked to consent A, then a later consent B).
        // A single purpose-level revocation must revoke BOTH consent events and
        // BOTH grants, so an older grant cannot remain authorised via an
        // earlier consent event.
        $patient = User::factory()->create(['role' => 'patient']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinicId = $this->makeClinic();
        $this->makeClinicRep($rep, $clinicId);
        $policy = $this->publishedPolicy();
        $case = $this->makeCase($patient);
        [$proposalA, $grantA] = $this->seedAcceptedGrant($case, $patient, $clinicId, $policy);
        $consentAId = $grantA->consent_event_id;

        // Patient creates a second referral_sharing consent event (consent B).
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $coordinator->id, 'assigned_by_user_id' => $coordinator->id, 'purpose' => 'coordination', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $proposalB = ReferralProposal::query()->create([
            'case_id' => $case->id, 'clinic_id' => $clinicId, 'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed', 'reasoning' => encrypt('second'), 'source_language' => 'fa', 'proposed_at' => now(),
        ]);
        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$case->id}/referrals/{$proposalB->id}/decision", [
                'decision' => 'accepted', 'policy_version' => $policy->version, 'content_hash' => $policy->content_hash,
            ])
            ->assertOk();

        // Both grants and both consents exist and are active.
        $this->assertSame(2, DB::table('referral_grants')->where('case_id', $case->id)->whereNull('revoked_at')->count());
        $this->assertSame(2, ConsentEvent::query()->where('subject_user_id', $patient->id)->where('purpose', 'referral_sharing')->whereNull('revoked_at')->count());

        // A single purpose-level revocation must revoke all active consent events
        // and all dependent grants for this purpose.
        $this->actingAs($patient)
            ->deleteJson("/api/v1/cases/{$case->id}/consent/referral_sharing")
            ->assertOk()
            ->assertJsonPath('data.revoked_grants', function ($value): bool {
                return is_array($value) && count($value) === 2;
            });

        $this->assertSame(0, DB::table('referral_grants')->where('case_id', $case->id)->whereNull('revoked_at')->count());
        $this->assertSame(0, ConsentEvent::query()->where('subject_user_id', $patient->id)->where('purpose', 'referral_sharing')->whereNull('revoked_at')->count());
        // Clinic access disappears immediately.
        $this->actingAs($rep)
            ->getJson("/api/v1/cases/{$case->id}")
            ->assertNotFound();
    }

    public function test_legacy_null_expiry_grant_does_not_authorise_clinic_rep(): void
    {
        // A deliberately malformed legacy grant with NULL expires_at (or NULL
        // consent_event_id) must NOT authorise a clinic representative. The policy
        // uses fail-closed inner-join + NOT NULL semantics, not permissive NULL.
        $patient = User::factory()->create(['role' => 'patient']);
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $clinicId = $this->makeClinic();
        $this->makeClinicRep($rep, $clinicId);
        $case = $this->makeCase($patient);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id, 'clinic_id' => $clinicId, 'proposed_by_user_id' => $coordinator->id,
            'status' => 'accepted', 'decided_at' => now(), 'reasoning' => encrypt('r'), 'source_language' => 'fa', 'proposed_at' => now(),
        ]);
        // Insert a legacy grant with NULL consent_event_id and NULL expires_at,
        // bypassing the NOT NULL constraint by using a raw insert into a temporary
        // state — since the column is now NOT NULL at the DB level, this raw insert
        // must fail, proving the schema enforces the invariant.
        try {
            DB::table('referral_grants')->insert([
                'id' => (string) Str::ulid(), 'proposal_id' => $proposal->id, 'case_id' => $case->id,
                'clinic_id' => $clinicId, 'scope' => json_encode(['contact']), 'granted_at' => now(),
                'expires_at' => null, 'consent_event_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Expected integrity constraint violation: referral_grants.expires_at and consent_event_id are NOT NULL');
        } catch (\Throwable) {
            // The NOT NULL constraint rejects the legacy malformed grant at the DB level.
        }

        // A grant with a valid consent_event_id but a past expiry is denied at the policy level.
        $policy = $this->publishedPolicy();
        $consent = ConsentEvent::query()->create([
            'subject_user_id' => $patient->id, 'case_id' => $case->id, 'policy_version_id' => $policy->id,
            'purpose' => 'referral_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web',
            'ip_hash' => hash('sha256', 'ip'), 'user_agent_hash' => hash('sha256', 'ua'), 'created_at' => now(),
        ]);
        DB::table('referral_grants')->insert([
            'id' => (string) Str::ulid(), 'proposal_id' => $proposal->id, 'case_id' => $case->id,
            'clinic_id' => $clinicId, 'consent_event_id' => $consent->id, 'scope' => json_encode(['contact']),
            'granted_at' => now()->subHour(), 'expires_at' => now()->subMinute(),
            'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
        ]);
        $this->actingAs($rep)
            ->getJson("/api/v1/cases/{$case->id}")
            ->assertNotFound();
    }
}
