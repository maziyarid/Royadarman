<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Jobs\ProcessOutboxEvent;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientCaseIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_is_disabled_server_side(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/cases/draft', [], ['Idempotency-Key' => 'disabled-1'])->assertServiceUnavailable();
    }

    public function test_patient_creates_and_submits_one_idempotent_case_with_localised_consent(): void
    {
        Queue::fake([ProcessOutboxEvent::class]);
        config()->set('royadarman.intake_enabled', true);
        $user = User::factory()->create(['role' => 'patient', 'locale' => 'fa', 'phone' => '09121234567', 'phone_hash' => hash('sha256', 'patient')]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        $policy = PolicyVersion::query()->create(['policy_key' => 'case_coordination', 'version' => 'approved-1', 'locale' => 'fa', 'content' => 'متن رضایت', 'content_hash' => hash('sha256', 'متن رضایت'), 'published_at' => now()]);
        $payload = ['service_type' => 'opg_review', 'name' => 'سارا', 'budget_band' => 'balanced', 'budget_input_unit' => 'toman', 'source_language' => 'fa'];

        $first = $this->actingAs($user)->postJson('/api/v1/cases/draft', $payload, ['Idempotency-Key' => 'draft-'.Str::uuid()])->assertCreated();
        $caseId = $first->json('data.id');
        $submitKey = 'submit-'.Str::uuid();
        $submit = ['version' => 1, 'policy_version' => $policy->version, 'content_hash' => $policy->content_hash];
        $this->postJson("/api/v1/cases/{$caseId}/submit", $submit, ['Idempotency-Key' => $submitKey])->assertOk()->assertJsonPath('data.status', CaseStatus::Submitted->value);
        $this->postJson("/api/v1/cases/{$caseId}/submit", $submit, ['Idempotency-Key' => $submitKey])->assertOk();

        $this->assertDatabaseCount('patient_cases', 1);
        $this->assertDatabaseCount('consent_events', 1);
        $this->assertDatabaseCount('outbox_events', 1);
        $this->assertDatabaseCount('case_assignments', 1);
        $this->assertDatabaseHas('patient_cases', ['id' => $caseId, 'current_coordinator_id' => $coordinator->id]);
        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $caseId,
            'assignee_user_id' => $coordinator->id,
            'purpose' => 'coordination',
            'assigned_by_user_id' => null,
            'released_at' => null,
        ]);
        $this->assertDatabaseMissing('patient_cases', ['patient_name' => 'سارا']);
    }

    public function test_substituted_coordination_policy_hash_is_rejected_before_consent_or_submission(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $user = User::factory()->create(['role' => 'patient', 'locale' => 'fa', 'phone' => '09121234567', 'phone_hash' => hash('sha256', 'hash-mismatch')]);
        User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        $content = 'approved coordination text';
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'case_coordination',
            'version' => 'approved-hash-1',
            'locale' => 'fa',
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'published_at' => now(),
        ]);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-HASHMIS1',
            'patient_user_id' => $user->id,
            'service_type' => 'guidance_referral',
            'status' => 'draft',
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => $user->phone_hash,
            'budget_band' => 'call',
            'source_language' => 'fa',
            'version' => 1,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/cases/{$case->id}/submit", [
                'version' => 1,
                'policy_version' => $policy->version,
                'content_hash' => hash('sha256', 'different displayed text'),
            ], ['Idempotency-Key' => 'hash-mismatch'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'consent.policy_mismatch');

        $this->assertDatabaseHas('patient_cases', ['id' => $case->id, 'status' => 'draft', 'version' => 1]);
        $this->assertDatabaseMissing('consent_events', ['case_id' => $case->id, 'purpose' => 'case_coordination']);
        $this->assertDatabaseMissing('case_assignments', ['case_id' => $case->id]);
        $this->assertDatabaseMissing('outbox_events', ['aggregate_id' => $case->id, 'event_type' => 'case.submitted']);
    }

    public function test_missing_consent_translation_blocks_submission(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $user = User::factory()->create(['role' => 'patient', 'phone' => '09121234567', 'phone_hash' => hash('sha256', 'missing')]);
        $case = PatientCase::query()->create(['public_reference' => 'RD-MISSING1', 'patient_user_id' => $user->id, 'service_type' => 'guidance_referral', 'status' => 'draft', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => $user->phone_hash, 'budget_band' => 'call', 'source_language' => 'ar']);
        $this->actingAs($user)->postJson("/api/v1/cases/{$case->id}/submit", ['version' => 1, 'policy_version' => 'missing', 'content_hash' => hash('sha256', 'missing')], ['Idempotency-Key' => 'missing-consent'])->assertServiceUnavailable()->assertJsonPath('error.code', 'error.consent.translation_unavailable');
    }
}
