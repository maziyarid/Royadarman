<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\ServiceType;
use App\Domain\Cases\Services\CaseWorkflow;
use App\Models\PatientCase;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_transition_is_persisted_and_audited(): void
    {
        $actor = User::factory()->create();
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-TEST0001',
            'service_type' => ServiceType::GuidanceReferral,
            'status' => CaseStatus::Submitted,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', '09121234567'),
            'budget_band' => 'call',
        ]);

        app(CaseWorkflow::class)->transition($case, CaseStatus::AwaitingContact, $actor, 'صف جدید');

        $this->assertDatabaseHas('patient_cases', ['id' => $case->id, 'status' => 'awaiting_contact']);
        $this->assertDatabaseHas('case_status_events', ['case_id' => $case->id, 'to_status' => 'awaiting_contact']);
        $this->assertDatabaseHas('audit_events', ['resource_id' => $case->id, 'action' => 'case.status.transitioned']);
    }

    public function test_invalid_transition_is_rejected_without_partial_history(): void
    {
        $actor = User::factory()->create();
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-TEST0002',
            'service_type' => ServiceType::GuidanceReferral,
            'status' => CaseStatus::Submitted,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', '09121234567'),
            'budget_band' => 'call',
        ]);

        try {
            app(CaseWorkflow::class)->transition($case, CaseStatus::Closed, $actor);
            $this->fail('Expected an invalid transition to throw.');
        } catch (DomainException) {
            $this->assertDatabaseHas('patient_cases', ['id' => $case->id, 'status' => 'submitted']);
            $this->assertDatabaseCount('case_status_events', 0);
            $this->assertDatabaseCount('audit_events', 0);
        }
    }
}
