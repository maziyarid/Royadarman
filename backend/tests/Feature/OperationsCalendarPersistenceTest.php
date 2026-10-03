<?php

namespace Tests\Feature;

use App\Models\ConsentEvent;
use App\Models\CoordinationTask;
use App\Models\HomeServiceRequest;
use App\Models\PatientCase;
use App\Models\PolicyVersion;
use App\Models\ReferralGrant;
use App\Models\ReferralProposal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OperationsCalendarPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public static function months(): array
    {
        return [
            'leap Esfand' => ['1403-12', '2025-02-18 20:30:00', '2025-03-20 20:30:00', 30],
            'normal Esfand' => ['1404-12', '2026-02-19 20:30:00', '2026-03-20 20:30:00', 29],
            'Saturday Farvardin' => ['1405-01', '2026-03-20 20:30:00', '2026-04-20 20:30:00', 31],
            'historic spring clock change' => ['1401-01', '2022-03-20 20:30:00', '2022-04-20 19:30:00', 31],
            'historic autumn clock change' => ['1401-06', '2022-08-22 19:30:00', '2022-09-22 20:30:00', 31],
        ];
    }

    #[DataProvider('months')]
    public function test_persisted_events_match_half_open_month_and_grid_without_private_fields(string $month, string $start, string $end, int $days): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $other = User::factory()->create(['role' => 'coordinator']);
        $case = $this->assignedCase($coordinator);
        $foreign = $this->assignedCase($other);
        $begin = CarbonImmutable::parse($start, 'UTC');
        $finish = CarbonImmutable::parse($end, 'UTC');
        foreach ([$begin->subSecond(), $begin, $finish->subSecond(), $finish, $finish->addSecond()] as $at) {
            $this->eventsAt($case, $coordinator, $at);
        }
        $this->eventsAt($foreign, $other, $begin);
        CoordinationTask::query()->create(['case_id' => $case->id, 'assignee_user_id' => $other->id, 'task_type' => 'follow_up', 'status' => 'open', 'due_at' => $begin]);

        $response = $this->actingAs($coordinator)->get('/fa/panel/calendar?jmonth='.$month);
        $response->assertOk()->assertViewHas('summary', ['tasks' => 2, 'home_service' => 2, 'referral_expiry' => 2, 'total' => 6]);
        $response->assertViewHas('serverWindow', fn ($window) => is_array($window) && $window['start_utc'] === $start && $window['end_utc'] === $end && $window['day_count'] === $days && $window['half_open'] === true);
        $grid = $response->viewData('days');
        $events = $response->viewData('events');
        $this->assertCount($days, $grid);
        $this->assertCount(6, $events);
        $this->assertSame(6, $grid->sum(fn ($day) => $day['events']->count()));
        $this->assertSame(6, $events->map(fn ($event) => $event['kind'].'|'.$event['at']->utc()->format('Y-m-d H:i:s'))->unique()->count());
        $response->assertDontSee('PRIVATE-PATIENT')->assertDontSee('PRIVATE-NOTE')->assertDontSee($foreign->public_reference);
        foreach ($events as $event) {
            $this->assertStringNotContainsString($foreign->id, $event['url']);
            $this->assertSame('Asia/Tehran', $event['at']->timezoneName);
        }
        if ($month === '1405-01') {
            $this->assertSame(0, $response->viewData('leading'));
        }

        $case->update(['current_coordinator_id' => $other->id]);
        $this->get('/fa/panel/calendar?jmonth='.$month)->assertOk()->assertViewHas('summary', ['tasks' => 0, 'home_service' => 0, 'referral_expiry' => 0, 'total' => 0]);
    }

    public function test_invalid_month_roles_and_demo_are_denied_and_revoked_referral_is_absent(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $case = $this->assignedCase($coordinator);
        $this->eventsAt($case, $coordinator, CarbonImmutable::parse('2026-03-20 20:30:00', 'UTC'));
        ReferralGrant::query()->where('case_id', $case->id)->update(['revoked_at' => now()]);
        $this->actingAs($coordinator)->get('/fa/panel/calendar?jmonth=1405-01')->assertOk()->assertViewHas('summary', ['tasks' => 1, 'home_service' => 1, 'referral_expiry' => 0, 'total' => 2]);
        foreach (['1405-13', '1405-00', '1199-12', '1601-01', '1405-1', 'nope'] as $month) {
            $this->get('/fa/panel/calendar?jmonth='.$month)->assertUnprocessable();
        }
        foreach (['patient', 'owner', 'tech_admin', 'clinician', 'clinic_rep'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/fa/panel/calendar')->assertForbidden();
        }
        $this->actingAs($coordinator)->withSession(['panel_demo' => true])->get('/fa/panel/calendar')->assertForbidden();
    }

    private function assignedCase(User $coordinator): PatientCase
    {
        $patient = User::factory()->create(['role' => 'patient', 'name' => 'PRIVATE-PATIENT']);

        return PatientCase::query()->create(['public_reference' => 'RD-'.strtoupper(Str::random(8)), 'patient_user_id' => $patient->id, 'current_coordinator_id' => $coordinator->id, 'service_type' => 'guidance_referral', 'status' => 'in_coordination', 'patient_name' => 'PRIVATE-PATIENT', 'patient_mobile' => '09120000000', 'patient_mobile_hash' => hash('sha256', Str::random()), 'budget_band' => 'call']);
    }

    private function eventsAt(PatientCase $case, User $coordinator, CarbonImmutable $at): void
    {
        CoordinationTask::query()->create(['case_id' => $case->id, 'assignee_user_id' => $coordinator->id, 'task_type' => 'follow_up', 'status' => 'open', 'operational_note' => 'PRIVATE-NOTE', 'due_at' => $at]);
        HomeServiceRequest::query()->create(['case_id' => $case->id, 'patient_user_id' => $case->patient_user_id, 'tehran_area' => 'synthetic', 'status' => 'scheduled', 'scheduled_for' => $at]);
        $clinicId = (string) Str::ulid();
        DB::table('clinics')->insert(['id' => $clinicId, 'name' => 'Synthetic clinic', 'city' => 'Tehran', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $proposal = ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinicId, 'proposed_by_user_id' => $coordinator->id, 'status' => 'accepted', 'reasoning' => 'PRIVATE-NOTE', 'source_language' => 'fa', 'proposed_at' => now()]);
        $policy = PolicyVersion::query()->create(['policy_key' => 'referral_sharing', 'version' => (string) Str::ulid(), 'locale' => 'fa', 'content' => 'synthetic consent', 'content_hash' => hash('sha256', 'synthetic consent'), 'published_at' => now()]);
        $consent = ConsentEvent::query()->create(['subject_user_id' => $case->patient_user_id, 'case_id' => $case->id, 'policy_version_id' => $policy->id, 'purpose' => 'referral_sharing', 'decision' => 'accepted', 'locale' => 'fa', 'channel' => 'web', 'ip_hash' => hash('sha256', 'synthetic-ip'), 'user_agent_hash' => hash('sha256', 'synthetic-agent'), 'created_at' => now()]);
        ReferralGrant::query()->create(['proposal_id' => $proposal->id, 'consent_event_id' => $consent->id, 'case_id' => $case->id, 'clinic_id' => $clinicId, 'scope' => ['contact'], 'granted_at' => now(), 'expires_at' => $at]);
    }
}
