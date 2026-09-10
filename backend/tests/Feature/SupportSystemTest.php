<?php

namespace Tests\Feature;

use App\Domain\Support\Enums\ConversationStatus;
use App\Domain\Support\Enums\SupportCategory;
use App\Models\PatientCase;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupportSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_creates_a_support_conversation_with_first_message(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient)
            ->postJson('/api/v1/support', [
                'category' => SupportCategory::Coordination->value,
                'subject' => 'Need help with referral',
                'source_language' => 'fa',
                'message' => 'سلام، راهنمایی می‌خواستم',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ConversationStatus::Open->value);

        $this->assertDatabaseHas('support_conversations', [
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::Coordination->value,
            'status' => ConversationStatus::Open->value,
        ]);
        $this->assertDatabaseHas('support_messages', [
            'author_user_id' => $patient->id,
            'is_internal' => false,
        ]);
    }

    public function test_patient_cannot_link_support_conversation_to_another_patients_case(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $otherPatient = User::factory()->create(['role' => 'patient']);
        $otherCase = PatientCase::query()->create([
            'public_reference' => 'RD-OTHER-1',
            'patient_user_id' => $otherPatient->id,
            'service_type' => 'opg_review',
            'status' => 'submitted',
            'patient_mobile' => '09120000000',
            'patient_mobile_hash' => hash('sha256', 'other'),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'budget_input_unit' => 'toman',
            'currency' => 'IRR',
        ]);

        $this->actingAs($patient)
            ->postJson('/api/v1/support', [
                'case_id' => $otherCase->id,
                'category' => SupportCategory::Coordination->value,
                'source_language' => 'fa',
                'message' => 'trying to link someone else case',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('support_conversations', [
            'patient_user_id' => $patient->id,
            'case_id' => $otherCase->id,
        ]);
    }

    public function test_non_patient_cannot_create_support_conversation(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);

        $this->actingAs($coordinator)
            ->postJson('/api/v1/support', [
                'category' => SupportCategory::General->value,
                'source_language' => 'fa',
                'message' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_patient_can_reply_and_coordinator_sees_the_message(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Open->value,
            'priority' => 'normal',
            'assignee_user_id' => $coordinator->id,
            'opened_at' => now(),
            'source_language' => 'fa',
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/support/{$conversation->id}/messages", [
                'message' => 'second message',
                'source_language' => 'fa',
            ])
            ->assertCreated();

        $this->actingAs($coordinator)
            ->getJson("/api/v1/support/{$conversation->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages');
    }

    public function test_coordinator_internal_note_is_hidden_from_patient(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::InProgress->value,
            'priority' => 'normal',
            'assignee_user_id' => $coordinator->id,
            'opened_at' => now(),
            'source_language' => 'fa',
        ]);

        $this->actingAs($coordinator)
            ->postJson("/api/v1/support/{$conversation->id}/internal-notes", [
                'message' => 'internal staff note',
                'source_language' => 'en',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', true);

        $this->actingAs($patient)
            ->getJson("/api/v1/support/{$conversation->id}")
            ->assertOk()
            ->assertJsonMissing(['body' => 'internal staff note'])
            ->assertJsonCount(0, 'data.messages');
    }

    public function test_patient_cannot_add_internal_note(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Open->value,
            'priority' => 'normal',
            'opened_at' => now(),
            'source_language' => 'fa',
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/support/{$conversation->id}/internal-notes", [
                'message' => 'sneaky',
                'source_language' => 'fa',
            ])
            ->assertForbidden();
    }

    public function test_patient_cannot_view_another_patients_conversation(): void
    {
        $patientA = User::factory()->create(['role' => 'patient']);
        $patientB = User::factory()->create(['role' => 'patient']);
        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $patientA->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Open->value,
            'priority' => 'normal',
            'opened_at' => now(),
            'source_language' => 'fa',
        ]);

        $this->actingAs($patientB)
            ->getJson("/api/v1/support/{$conversation->id}")
            ->assertNotFound();
    }

    public function test_coordinator_can_transition_status_and_event_is_recorded(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Open->value,
            'priority' => 'normal',
            'assignee_user_id' => $coordinator->id,
            'opened_at' => now(),
            'source_language' => 'fa',
        ]);

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/support/{$conversation->id}/status", [
                'status' => ConversationStatus::InProgress->value,
                'reason' => 'picking up',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ConversationStatus::InProgress->value);

        $this->assertDatabaseHas('support_status_events', [
            'conversation_id' => $conversation->id,
            'from_status' => ConversationStatus::Open->value,
            'to_status' => ConversationStatus::InProgress->value,
        ]);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Closed->value,
            'priority' => 'normal',
            'assignee_user_id' => $coordinator->id,
            'opened_at' => now(),
            'source_language' => 'fa',
        ]);

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/support/{$conversation->id}/status", [
                'status' => ConversationStatus::Open->value,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'support.invalid_transition');
    }

    public function test_patient_list_only_returns_own_conversations(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $other = User::factory()->create(['role' => 'patient']);
        SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Open->value,
            'priority' => 'normal',
            'opened_at' => now(),
            'source_language' => 'fa',
            'subject' => 'mine',
        ]);
        SupportConversation::query()->create([
            'patient_user_id' => $other->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Open->value,
            'priority' => 'normal',
            'opened_at' => now(),
            'source_language' => 'fa',
            'subject' => 'theirs',
        ]);

        $this->actingAs($patient)
            ->getJson('/api/v1/support')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'mine')
            ->assertJsonMissing(['subject' => 'theirs']);
    }

    public function test_support_index_denies_clinician_and_clinic_rep_visibility_bypass(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Open->value,
            'priority' => 'normal',
            'opened_at' => now(),
            'source_language' => 'fa',
            'subject' => 'patient conversation',
        ]);

        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->actingAs($clinician)
            ->getJson('/api/v1/support')
            ->assertForbidden()
            ->assertJsonMissing(['subject' => 'patient conversation']);

        $clinicRep = User::factory()->create(['role' => 'clinic_rep']);
        $this->actingAs($clinicRep)
            ->getJson('/api/v1/support')
            ->assertForbidden()
            ->assertJsonMissing(['subject' => 'patient conversation']);
    }

    public function test_support_index_allows_owner_to_browse_all_conversations(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'category' => SupportCategory::General->value,
            'status' => ConversationStatus::Open->value,
            'priority' => 'normal',
            'opened_at' => now(),
            'source_language' => 'fa',
            'subject' => 'owner visible',
        ]);

        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)
            ->getJson('/api/v1/support')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'owner visible');
    }
}
