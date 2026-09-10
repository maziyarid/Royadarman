<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\HomeServiceStatus;
use App\Jobs\ProcessOutboxEvent;
use App\Models\HomeServiceRequest;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HomeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_advance_home_service_through_lifecycle(): void
    {
        config()->set('royadarman.intake_enabled', true);
        [$patient, $coordinator, $provider, $case, $home] = $this->setupHomeService();

        $this->actingAs($coordinator)
            ->postJson('/api/v1/home-services/'.$home->id.'/transition', ['status' => 'area_verified', 'version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'area_verified')
            ->assertJsonPath('data.version', 2);

        $this->actingAs($coordinator)
            ->postJson('/api/v1/home-services/'.$home->id.'/transition', ['status' => 'coordinator_review', 'version' => 2])
            ->assertOk();

        $this->actingAs($coordinator)
            ->postJson('/api/v1/home-services/'.$home->id.'/transition', ['status' => 'provider_requested', 'version' => 3, 'provider_user_id' => $provider->id])
            ->assertOk()
            ->assertJsonPath('data.provider_user_id', $provider->id);

        $this->actingAs($provider)
            ->postJson('/api/v1/home-services/'.$home->id.'/transition', ['status' => 'provider_accepted', 'version' => 4, 'provider_user_id' => $provider->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'provider_accepted');

        $this->actingAs($patient)
            ->postJson('/api/v1/home-services/'.$home->id.'/confirm', ['version' => 5])
            ->assertOk()
            ->assertJsonPath('data.status', 'patient_confirmed');
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        config()->set('royadarman.intake_enabled', true);
        [$patient, $coordinator, $provider, $case, $home] = $this->setupHomeService();

        // requested → completed is not allowed
        $this->actingAs($coordinator)
            ->postJson('/api/v1/home-services/'.$home->id.'/transition', ['status' => 'completed', 'version' => 1])
            ->assertStatus(422);
    }

    public function test_optimistic_concurrency_version_mismatch_returns_409(): void
    {
        config()->set('royadarman.intake_enabled', true);
        [$patient, $coordinator, $provider, $case, $home] = $this->setupHomeService();

        $this->actingAs($coordinator)
            ->postJson('/api/v1/home-services/'.$home->id.'/transition', ['status' => 'area_verified', 'version' => 99])
            ->assertStatus(409);
    }

    public function test_patient_cannot_transition_status_only_coordinator_or_clinic_rep(): void
    {
        config()->set('royadarman.intake_enabled', true);
        [$patient, $coordinator, $provider, $case, $home] = $this->setupHomeService();

        $this->actingAs($patient)
            ->postJson('/api/v1/home-services/'.$home->id.'/transition', ['status' => 'area_verified', 'version' => 1])
            ->assertNotFound();
    }

    public function test_patient_can_only_confirm_after_provider_accepted(): void
    {
        config()->set('royadarman.intake_enabled', true);
        [$patient, $coordinator, $provider, $case, $home] = $this->setupHomeService();

        // Still at 'requested' → cannot confirm
        $this->actingAs($patient)
            ->postJson('/api/v1/home-services/'.$home->id.'/confirm', ['version' => 1])
            ->assertStatus(422);
    }

    public function test_patient_cannot_view_another_patients_home_service(): void
    {
        config()->set('royadarman.intake_enabled', true);
        [$patient, $coordinator, $provider, $case, $home] = $this->setupHomeService();
        $otherPatient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($otherPatient)
            ->getJson('/api/v1/home-services/'.$home->id)
            ->assertNotFound();
    }

    public function test_status_events_are_recorded(): void
    {
        config()->set('royadarman.intake_enabled', true);
        [$patient, $coordinator, $provider, $case, $home] = $this->setupHomeService();

        $this->actingAs($coordinator)
            ->postJson('/api/v1/home-services/'.$home->id.'/transition', ['status' => 'area_verified', 'version' => 1])
            ->assertOk();

        $event = $home->statusEvents()->latest('created_at')->first();
        $this->assertSame('requested', $event->from_status);
        $this->assertSame('area_verified', $event->to_status);
        $this->assertSame($coordinator->id, $event->actor_user_id);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: PatientCase, 4: HomeServiceRequest}
     */
    private function setupHomeService(): array
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $provider = User::factory()->create(['role' => 'clinic_rep']);

        $case = PatientCase::query()->create([
            'public_reference' => 'RD-HOME-'.Str::random(5),
            'patient_user_id' => $patient->id,
            'service_type' => 'home_dentistry',
            'status' => 'submitted',
            'patient_name' => $patient->name,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', 'home'),
            'tehran_area' => 'شرق',
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'budget_input_unit' => 'toman',
            'currency' => 'IRR',
        ]);

        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'assignee_user_id' => $coordinator->id,
            'assigned_by_user_id' => $coordinator->id,
            'purpose' => 'coordination',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $home = HomeServiceRequest::query()->create([
            'case_id' => $case->id,
            'patient_user_id' => $patient->id,
            'tehran_area' => 'شرق',
            'status' => HomeServiceStatus::Requested,
        ]);

        return [$patient, $coordinator, $provider, $case, $home];
    }

    public function test_intake_submission_creates_home_service_request_for_home_dentistry(): void
    {
        Queue::fake([ProcessOutboxEvent::class]);
        config()->set('royadarman.intake_enabled', true);
        $user = User::factory()->create(['role' => 'patient', 'locale' => 'fa', 'phone' => '09121234567', 'phone_hash' => hash('sha256', 'home-intake')]);
        PolicyVersion::query()->create(['policy_key' => 'case_coordination', 'version' => 'home-approved-1', 'locale' => 'fa', 'content' => 'متن رضایت', 'content_hash' => hash('sha256', 'متن رضایت'), 'published_at' => now()]);

        $draft = $this->actingAs($user)->postJson('/api/v1/cases/draft', [
            'service_type' => 'home_dentistry',
            'tehran_area' => 'east',
            'budget_band' => 'balanced',
            'budget_input_unit' => 'toman',
            'source_language' => 'fa',
        ], ['Idempotency-Key' => 'home-draft-'.Str::uuid()])->assertCreated();

        $caseId = $draft->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/cases/{$caseId}/submit", ['version' => 1, 'policy_version' => 'home-approved-1'], ['Idempotency-Key' => 'home-submit-'.Str::uuid()])
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatus::Submitted->value);

        $this->assertDatabaseHas('home_service_requests', [
            'case_id' => $caseId,
            'patient_user_id' => $user->id,
            'tehran_area' => 'east',
            'status' => HomeServiceStatus::Requested->value,
        ]);
    }

    public function test_intake_submission_does_not_create_home_service_for_non_home_dentistry(): void
    {
        Queue::fake([ProcessOutboxEvent::class]);
        config()->set('royadarman.intake_enabled', true);
        $user = User::factory()->create(['role' => 'patient', 'locale' => 'fa', 'phone' => '09121234567', 'phone_hash' => hash('sha256', 'opg-intake')]);
        PolicyVersion::query()->create(['policy_key' => 'case_coordination', 'version' => 'opg-approved-1', 'locale' => 'fa', 'content' => 'متن رضایت', 'content_hash' => hash('sha256', 'متن رضایت'), 'published_at' => now()]);

        $draft = $this->actingAs($user)->postJson('/api/v1/cases/draft', [
            'service_type' => 'opg_review',
            'budget_band' => 'balanced',
            'budget_input_unit' => 'toman',
            'source_language' => 'fa',
        ], ['Idempotency-Key' => 'opg-draft-'.Str::uuid()])->assertCreated();

        $caseId = $draft->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/cases/{$caseId}/submit", ['version' => 1, 'policy_version' => 'opg-approved-1'], ['Idempotency-Key' => 'opg-submit-'.Str::uuid()])
            ->assertOk();

        $this->assertDatabaseMissing('home_service_requests', ['case_id' => $caseId]);
    }
}
