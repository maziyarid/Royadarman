<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Coordination\Enums\ReferralLifecycleEventType;
use App\Domain\Coordination\Services\ReferralLifecycle;
use App\Jobs\ProcessOutboxEvent;
use App\Models\Clinic;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralLifecycleEvent;
use App\Models\ReferralProposal;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ReferralLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.intake_enabled', true);
        config()->set('royadarman.referral.grant_ttl_minutes', 43200);
        config()->set('royadarman.referral.proposal_sla_minutes', 1440);
    }

    private function makeCase(User $patient, User $coordinator, CaseStatus $status = CaseStatus::InCoordination): PatientCase
    {
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'current_coordinator_id' => $coordinator->id,
            'service_type' => 'guidance_referral',
            'status' => $status,
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
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

        return $case;
    }

    private function makeClinic(string $name = 'Partner Clinic'): Clinic
    {
        return Clinic::query()->create(['name' => $name, 'city' => 'Tehran', 'is_active' => true]);
    }

    public function test_propose_persists_proposed_and_offered_events_with_actor(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinic = $this->makeClinic();
        $case = $this->makeCase($patient, $coordinator);

        $id = $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/referral-proposals", [
                'clinic_id' => $clinic->id,
                'reasoning' => 'needs specialist',
                'source_language' => 'fa',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('referral_lifecycle_events', [
            'proposal_id' => $id,
            'case_id' => $case->id,
            'clinic_id' => $clinic->id,
            'event_type' => ReferralLifecycleEventType::Proposed->value,
            'actor_user_id' => $coordinator->id,
        ]);
        $this->assertDatabaseHas('referral_lifecycle_events', [
            'proposal_id' => $id,
            'event_type' => ReferralLifecycleEventType::Offered->value,
            'actor_user_id' => $coordinator->id,
        ]);
        $this->assertFalse(Schema::hasColumn('referral_lifecycle_events', 'updated_at'));
    }

    public function test_events_are_append_only(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinic = $this->makeClinic();
        $case = $this->makeCase($patient, $coordinator);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => 'needs specialist',
            'source_language' => 'fa',
            'proposed_at' => now(),
        ]);
        $event = app(ReferralLifecycle::class)->record($proposal, ReferralLifecycleEventType::Proposed, $coordinator);

        try {
            $event->update(['event_type' => ReferralLifecycleEventType::Viewed]);
            $this->fail('updating a lifecycle event must fail');
        } catch (RuntimeException $e) {
            $this->assertSame('referral lifecycle events are append-only', $e->getMessage());
        }

        try {
            $event->delete();
            $this->fail('deleting a lifecycle event must fail');
        } catch (RuntimeException $e) {
            $this->assertSame('referral lifecycle events are append-only', $e->getMessage());
        }

        $this->assertDatabaseHas('referral_lifecycle_events', [
            'id' => $event->id,
            'event_type' => ReferralLifecycleEventType::Proposed->value,
        ]);
    }

    public function test_reassign_and_override_require_a_reason(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinic = $this->makeClinic();
        $other = $this->makeClinic('Other Clinic');
        $case = $this->makeCase($patient, $coordinator);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => 'needs specialist',
            'source_language' => 'fa',
            'proposed_at' => now(),
        ]);
        $lifecycle = app(ReferralLifecycle::class);

        try {
            $lifecycle->reassign($proposal, $coordinator, $other->id, '   ');
            $this->fail('reassign without reason must fail');
        } catch (DomainException $e) {
            $this->assertSame('referral.reason_required', $e->getMessage());
        }

        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/referral-proposals/{$proposal->id}/override", [])
            ->assertUnprocessable();

        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/referral-proposals/{$proposal->id}/reassign", [
                'clinic_id' => $other->id,
                'reason' => 'clinic closed Fridays',
            ])
            ->assertOk()
            ->assertJsonPath('data.clinic_id', $other->id);

        $reassigned = ReferralLifecycleEvent::query()
            ->where('proposal_id', $proposal->id)
            ->where('event_type', ReferralLifecycleEventType::Reassigned->value)
            ->first();
        $this->assertNotNull($reassigned);
        $this->assertSame($coordinator->id, $reassigned->actor_user_id);
        $this->assertSame('clinic closed Fridays', $reassigned->reason);

        $this->actingAs($coordinator)
            ->postJson("/api/v1/staff/cases/{$case->id}/referral-proposals/{$proposal->id}/override", [
                'reason' => 'patient asked to pause',
            ])
            ->assertOk()
            ->assertJsonPath('data.withdrawn', true);

        $override = ReferralLifecycleEvent::query()
            ->where('proposal_id', $proposal->id)
            ->where('event_type', ReferralLifecycleEventType::CoordinatorOverride->value)
            ->first();
        $this->assertSame('patient asked to pause', $override->reason);
    }

    public function test_accept_and_decline_write_decision_events_and_keep_consent_rules(): void
    {
        Queue::fake([ProcessOutboxEvent::class]);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinic = $this->makeClinic();
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'referral_sharing',
            'version' => 'approved-1',
            'locale' => 'fa',
            'content' => 'referral sharing text',
            'content_hash' => hash('sha256', 'referral sharing text'),
            'published_at' => now(),
        ]);
        $acceptCase = $this->makeCase($patient, $coordinator, CaseStatus::ReferralProposed);
        $acceptProposal = ReferralProposal::query()->create([
            'case_id' => $acceptCase->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => 'needs specialist',
            'source_language' => 'fa',
            'proposed_at' => now(),
        ]);

        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$acceptCase->id}/referrals/{$acceptProposal->id}/decision", [
                'decision' => 'accepted',
                'policy_version' => $policy->version,
                'content_hash' => $policy->content_hash,
            ])
            ->assertOk();

        $this->assertDatabaseHas('referral_grants', ['proposal_id' => $acceptProposal->id]);
        $this->assertDatabaseHas('consent_events', [
            'subject_user_id' => $patient->id,
            'purpose' => 'referral_sharing',
            'case_id' => $acceptCase->id,
            'decision' => 'accepted',
        ]);
        $this->assertDatabaseHas('referral_lifecycle_events', [
            'proposal_id' => $acceptProposal->id,
            'event_type' => ReferralLifecycleEventType::Accepted->value,
            'actor_user_id' => $patient->id,
        ]);

        $declineCase = $this->makeCase($patient, $coordinator, CaseStatus::ReferralProposed);
        $declineProposal = ReferralProposal::query()->create([
            'case_id' => $declineCase->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => 'needs specialist',
            'source_language' => 'fa',
            'proposed_at' => now(),
        ]);
        $this->actingAs($patient)
            ->postJson("/api/v1/cases/{$declineCase->id}/referrals/{$declineProposal->id}/decision", [
                'decision' => 'declined',
            ])
            ->assertOk();
        $this->assertDatabaseMissing('referral_grants', ['proposal_id' => $declineProposal->id]);
        $this->assertDatabaseHas('referral_lifecycle_events', [
            'proposal_id' => $declineProposal->id,
            'event_type' => ReferralLifecycleEventType::Declined->value,
        ]);
    }

    public function test_patient_case_view_records_viewed_once(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinic = $this->makeClinic();
        $case = $this->makeCase($patient, $coordinator);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => 'needs specialist',
            'source_language' => 'fa',
            'proposed_at' => now(),
        ]);

        $this->actingAs($patient)->get('/fa/panel/cases/'.$case->id)->assertOk();
        $this->actingAs($patient)->get('/fa/panel/cases/'.$case->id)->assertOk();

        $this->assertSame(1, ReferralLifecycleEvent::query()
            ->where('proposal_id', $proposal->id)
            ->where('event_type', ReferralLifecycleEventType::Viewed->value)
            ->count());
    }

    public function test_coordinator_referral_sla_uses_event_timestamp_not_updated_at(): void
    {
        $now = CarbonImmutable::parse('2026-09-19 12:00:00');
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);

        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinic = $this->makeClinic();
        $case = $this->makeCase($patient, $coordinator);
        $proposal = ReferralProposal::query()->create([
            'case_id' => $case->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => 'needs specialist',
            'source_language' => 'fa',
            'proposed_at' => $now->subMinutes(200),
            'updated_at' => $now,
        ]);
        app(ReferralLifecycle::class)->record(
            $proposal,
            ReferralLifecycleEventType::Proposed,
            $coordinator,
            null,
            $now->subMinutes(200),
        );
        $proposal->forceFill(['updated_at' => $now])->save();

        $resp = $this->actingAs($coordinator)->getJson('/api/v1/dashboard')->assertOk();
        $row = collect($resp->json('data.referral_sla'))->firstWhere('proposal_id', $proposal->id);
        $this->assertNotNull($row);
        $this->assertSame(200, $row['wait_minutes']);
        $this->assertSame('ok', $row['sla_band']);
        $this->assertNull($row['expiry_state']);

        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
    }

    public function test_silent_loss_and_expiry_are_surfaced_to_coordinators(): void
    {
        $now = CarbonImmutable::parse('2026-09-19 12:00:00');
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);
        config()->set('royadarman.referral.proposal_sla_minutes', 60);

        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinic = $this->makeClinic();
        $silentCase = $this->makeCase($patient, $coordinator);
        $silent = ReferralProposal::query()->create([
            'case_id' => $silentCase->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => 'needs specialist',
            'source_language' => 'fa',
            'proposed_at' => $now->subMinutes(90),
        ]);
        app(ReferralLifecycle::class)->record(
            $silent,
            ReferralLifecycleEventType::Proposed,
            $coordinator,
            null,
            $now->subMinutes(90),
        );

        $expiredCase = $this->makeCase($patient, $coordinator);
        $expired = ReferralProposal::query()->create([
            'case_id' => $expiredCase->id,
            'clinic_id' => $clinic->id,
            'proposed_by_user_id' => $coordinator->id,
            'status' => 'proposed',
            'reasoning' => 'needs specialist',
            'source_language' => 'fa',
            'proposed_at' => $now->subMinutes(90),
        ]);
        $lifecycle = app(ReferralLifecycle::class);
        $lifecycle->record($expired, ReferralLifecycleEventType::Proposed, $coordinator, null, $now->subMinutes(90));
        $lifecycle->record($expired, ReferralLifecycleEventType::Viewed, $patient, null, $now->subMinutes(80));

        $resp = $this->actingAs($coordinator)->getJson('/api/v1/dashboard')->assertOk();
        $rows = collect($resp->json('data.referral_sla'));

        $silentRow = $rows->firstWhere('proposal_id', $silent->id);
        $this->assertSame('silent_loss', $silentRow['expiry_state']);
        $this->assertDatabaseHas('referral_lifecycle_events', [
            'proposal_id' => $silent->id,
            'event_type' => ReferralLifecycleEventType::SilentLoss->value,
        ]);

        $expiredRow = $rows->firstWhere('proposal_id', $expired->id);
        $this->assertSame('expired', $expiredRow['expiry_state']);
        $this->assertDatabaseHas('referral_lifecycle_events', [
            'proposal_id' => $expired->id,
            'event_type' => ReferralLifecycleEventType::Expired->value,
        ]);

        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
    }
}
