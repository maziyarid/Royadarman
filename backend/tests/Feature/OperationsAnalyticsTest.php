<?php

namespace Tests\Feature;

use App\Domain\Coordination\Enums\ReferralLifecycleEventType;
use App\Domain\Coordination\Services\ReferralLifecycle;
use App\Models\Clinic;
use App\Models\CoordinationTask;
use App\Models\HomeServiceRequest;
use App\Models\PatientCase;
use App\Models\ReferralLifecycleEvent;
use App\Models\ReferralProposal;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OperationsAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-03 12:00:00 UTC');
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->patient = User::factory()->create(['role' => 'patient']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_support_open_counts_all_enum_backed_open_states_and_home_completed_remains_correct(): void
    {
        foreach (['open', 'in_progress', 'awaiting_patient', 'reopened', 'resolved', 'closed'] as $status) {
            $this->support(now()->subDay(), ['status' => $status]);
        }
        $case = $this->caseAt(now()->subDay());
        HomeServiceRequest::query()->create(['case_id' => $case->id, 'patient_user_id' => $this->patient->id, 'tehran_area' => 'synthetic', 'status' => 'completed', 'created_at' => now()->subDay()]);

        $this->report()->assertViewHas('summary', fn ($summary) => $summary['support_open'] === 4 && $summary['home_completed'] === 1)
            ->assertViewHas('homeStatuses', fn ($counts) => $counts->get('completed') === 1);
    }

    public function test_enum_backed_home_completed_and_case_statuses_have_a_valid_positive_path(): void
    {
        $case = $this->caseAt(now()->subDay());
        HomeServiceRequest::query()->create(['case_id' => $case->id, 'patient_user_id' => $this->patient->id, 'tehran_area' => 'synthetic', 'status' => 'completed', 'created_at' => now()->subDay()]);
        $this->report()->assertViewHas('summary', fn ($summary) => $summary['home_completed'] === 1)
            ->assertViewHas('caseStatuses', fn ($counts) => $counts->all() === ['submitted' => 1]);
    }

    public function test_malformed_status_text_becomes_only_unknown_count_bucket(): void
    {
        $case = $this->caseAt(now()->subDay());
        DB::table('patient_cases')->where('id', $case->id)->update(['status' => 'PRIVATE-STATUS-TEXT']);
        $this->report()->assertOk()->assertViewHas('caseStatuses', fn ($counts) => $counts->all() === ['unknown' => 1])
            ->assertDontSee('PRIVATE-STATUS-TEXT');
    }

    public function test_actual_referral_withdrawal_expiry_and_silent_loss_have_effective_count_categories_without_writes(): void
    {
        $until = now()->toImmutable();
        $clinic = Clinic::query()->create(['name' => 'Synthetic clinic', 'city' => 'tehran', 'is_active' => true]);
        $case = $this->caseAt(now()->subDays(2));
        $make = fn (string $status = 'proposed') => ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $this->owner->id, 'status' => $status, 'reasoning' => 'PRIVATE-REFERRAL-REASON', 'source_language' => 'en', 'proposed_at' => $until->subDays(2)]);
        $withdrawn = $make();
        $expired = $make();
        $silent = $make();
        $accepted = $make('accepted');
        $declined = $make('declined');
        $lifecycle = app(ReferralLifecycle::class);
        Carbon::setTestNow($until->subSecond());
        $lifecycle->recordViewed($expired, $this->owner);
        $this->assertNotNull($lifecycle->surfaceExpiry($expired));
        $this->assertNotNull($lifecycle->surfaceExpiry($silent));
        $this->assertNull($lifecycle->surfaceExpiry($accepted));
        $this->assertNull($lifecycle->surfaceExpiry($declined));
        $lifecycle->overrideWithdraw($withdrawn, $this->owner, 'PRIVATE-WITHDRAWAL-REASON');
        $this->assertSame('proposed', $withdrawn->fresh()->status);
        $this->assertSame('proposed', $expired->fresh()->status);
        $events = DB::table('referral_lifecycle_events')->count();
        Carbon::setTestNow($until);
        $this->report()->assertOk()
            ->assertViewHas('referralStatuses', fn ($counts) => $counts->all() === ['accepted' => 1, 'declined' => 1, 'expired' => 1, 'silent_loss' => 1, 'withdrawn' => 1])
            ->assertViewHas('summary', fn ($summary) => $summary['referrals'] === 5 && $summary['referrals_accepted'] === 1)
            ->assertDontSee('PRIVATE-REFERRAL-REASON')->assertDontSee('PRIVATE-WITHDRAWAL-REASON');
        $this->assertSame($events, DB::table('referral_lifecycle_events')->count());
        $this->assertSame('proposed', $expired->fresh()->status);
    }

    public function test_referral_expiry_evidence_at_or_after_until_is_not_counted_and_withdrawal_takes_priority(): void
    {
        $until = now()->toImmutable();
        $clinic = Clinic::query()->create(['name' => 'Synthetic clinic', 'city' => 'tehran', 'is_active' => true]);
        $case = $this->caseAt(now()->subDays(2));
        $lifecycle = app(ReferralLifecycle::class);
        foreach ([$until, $until->addSecond()] as $at) {
            $proposal = ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $this->owner->id, 'status' => 'proposed', 'reasoning' => 'synthetic', 'source_language' => 'en', 'proposed_at' => $until->subDays(2)]);
            Carbon::setTestNow($at);
            $this->assertNotNull($lifecycle->surfaceExpiry($proposal));
        }
        $withdrawn = ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $this->owner->id, 'status' => 'proposed', 'reasoning' => 'synthetic', 'source_language' => 'en', 'proposed_at' => $until->subDays(2)]);
        Carbon::setTestNow($until->subSecond());
        $this->assertNotNull($lifecycle->surfaceExpiry($withdrawn));
        $lifecycle->overrideWithdraw($withdrawn, $this->owner, 'synthetic withdrawal');
        Carbon::setTestNow($until);
        $this->report()->assertOk()->assertViewHas('referralStatuses', fn ($counts) => $counts->all() === ['proposed' => 2, 'withdrawn' => 1]);
    }

    public function test_real_patient_decline_preserves_decision_category_after_recorded_sla_loss(): void
    {
        $until = now()->toImmutable();
        $clinic = Clinic::query()->create(['name' => 'Synthetic clinic', 'city' => 'tehran', 'is_active' => true]);
        $case = $this->caseAt(now()->subDays(2));
        $proposal = ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $this->owner->id, 'status' => 'proposed', 'reasoning' => 'synthetic', 'source_language' => 'en', 'proposed_at' => $until->subDays(2)]);
        Carbon::setTestNow($until->subSecond());
        $this->assertNotNull(app(ReferralLifecycle::class)->surfaceExpiry($proposal));
        $this->actingAs($this->patient)->postJson('/api/v1/cases/'.$case->id.'/referrals/'.$proposal->id.'/decision', ['decision' => 'declined'])
            ->assertOk()->assertJsonPath('data.status', 'declined');
        Carbon::setTestNow($until);
        $this->report()->assertOk()->assertViewHas('referralStatuses', fn ($counts) => $counts->all() === ['declined' => 1]);
    }

    public function test_unverified_raw_referral_statuses_do_not_invent_terminal_lifecycle_meaning(): void
    {
        $clinic = Clinic::query()->create(['name' => 'Synthetic clinic', 'city' => 'tehran', 'is_active' => true]);
        $case = $this->caseAt(now()->subDay());
        foreach (['rejected', 'withdrawn', 'expired', 'silent_loss'] as $status) {
            ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $this->owner->id, 'status' => $status, 'reasoning' => 'synthetic', 'source_language' => 'en', 'proposed_at' => now()->subDay()]);
        }
        $this->report()->assertOk()->assertViewHas('referralStatuses', fn ($counts) => $counts->all() === ['unknown' => 4]);
    }

    public function test_sla_evidence_is_proposal_wide_after_reassignment_but_cannot_use_a_foreign_case_event(): void
    {
        $until = now()->toImmutable();
        $firstClinic = Clinic::query()->create(['name' => 'First synthetic clinic', 'city' => 'tehran', 'is_active' => true]);
        $nextClinic = Clinic::query()->create(['name' => 'Next synthetic clinic', 'city' => 'tehran', 'is_active' => true]);
        $case = $this->caseAt(now()->subDays(2));
        $foreignCase = $this->caseAt(now()->subDays(2));
        $make = fn () => ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $firstClinic->id, 'proposed_by_user_id' => $this->owner->id, 'status' => 'proposed', 'reasoning' => 'synthetic', 'source_language' => 'en', 'proposed_at' => $until->subDays(2)]);
        $reassigned = $make();
        $foreignEvidence = $make();
        $bothFlags = $make();
        Carbon::setTestNow($until->subSecond());
        $lifecycle = app(ReferralLifecycle::class);
        $lifecycle->recordViewed($reassigned, $this->owner);
        $this->assertNotNull($lifecycle->surfaceExpiry($reassigned));
        $lifecycle->reassign($reassigned, $this->owner, $nextClinic->id, 'PRIVATE-REASSIGNMENT');
        $this->assertSame($nextClinic->id, $reassigned->fresh()->clinic_id);
        ReferralLifecycleEvent::query()->create(['proposal_id' => $foreignEvidence->id, 'case_id' => $foreignCase->id, 'clinic_id' => $firstClinic->id, 'event_type' => ReferralLifecycleEventType::Expired, 'created_at' => now()]);
        $lifecycle->record($bothFlags, ReferralLifecycleEventType::Expired, null);
        $lifecycle->record($bothFlags, ReferralLifecycleEventType::SilentLoss, null);
        Carbon::setTestNow($until);
        $this->report()->assertOk()->assertViewHas('referralStatuses', fn ($counts) => $counts->all() === ['expired' => 1, 'proposed' => 1, 'silent_loss' => 1])
            ->assertDontSee('PRIVATE-REASSIGNMENT');
    }

    public function test_historical_dst_window_and_saturday_buckets_use_named_tehran_zone(): void
    {
        Carbon::setTestNow('2022-04-02 12:00:00 UTC');
        $this->caseAt(Carbon::parse('2022-03-03 12:59:59 UTC'));
        $this->caseAt(Carbon::parse('2022-03-03 13:00:00 UTC'));
        $this->report()->assertOk()->assertViewHas('reportWindow', fn ($window) => $window['since'] === '2022-03-03T13:00:00+00:00')
            ->assertViewHas('summary', fn ($summary) => $summary['cases'] === 1)
            ->assertViewHas('caseTrend', fn ($trend) => $trend->first() === ['label' => '2022-02-26', 'count' => 1] && $trend->last()['label'] === '2022-04-02');
    }

    public function test_paged_date_only_reads_preserve_exact_totals_across_five_hundred_row_boundary(): void
    {
        $at = now()->subDay();
        $cases = [];
        $conversations = [];
        for ($index = 0; $index < 501; $index++) {
            $cases[] = ['id' => (string) Str::ulid(), 'public_reference' => 'RD-PAGED-'.$index, 'service_type' => 'opg_review', 'status' => 'submitted', 'patient_mobile' => 'synthetic-encrypted-unused', 'patient_mobile_hash' => hash('sha256', 'page-'.$index), 'budget_band' => 'call', 'created_at' => $at, 'updated_at' => $at];
            $conversations[] = ['id' => (string) Str::ulid(), 'patient_user_id' => $this->patient->id, 'status' => 'open', 'opened_at' => $at, 'first_response_at' => $at->copy()->addMinutes(7), 'created_at' => $at, 'updated_at' => $at];
        }
        DB::table('patient_cases')->insert($cases);
        DB::table('support_conversations')->insert($conversations);
        $this->report()->assertOk()->assertViewHas('summary', fn ($summary) => $summary['cases'] === 501 && $summary['support_open'] === 501 && $summary['avg_first_response_minutes'] === 7)
            ->assertViewHas('caseTrend', fn ($trend) => $trend->sum('count') === 501)
            ->assertViewHas('supportTrend', fn ($trend) => $trend->sum('count') === 501);
    }

    public function test_all_cohorts_exclude_old_exact_until_and_future_records_and_include_exact_since(): void
    {
        $since = now()->subDays(30);
        $clinic = Clinic::query()->create(['name' => 'Synthetic clinic', 'city' => 'tehran', 'is_active' => true]);
        foreach ([$since->copy()->subSecond(), $since, now()->subSecond(), now(), now()->addSecond()] as $at) {
            $case = $this->caseAt($at);
            $this->support($at);
            HomeServiceRequest::query()->create(['case_id' => $case->id, 'patient_user_id' => $this->patient->id, 'tehran_area' => 'synthetic', 'status' => 'completed', 'created_at' => $at]);
            ReferralProposal::query()->create(['case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $this->owner->id, 'status' => 'accepted', 'reasoning' => 'PRIVATE-REASON', 'source_language' => 'en', 'proposed_at' => $at]);
            CoordinationTask::query()->create(['case_id' => $case->id, 'assignee_user_id' => $this->owner->id, 'task_type' => 'follow_up', 'status' => 'open', 'due_at' => now()->subSecond()])->forceFill(['created_at' => $at])->save();
        }
        $this->report()->assertViewHas('summary', function ($summary): bool {
            foreach (['cases', 'support_opened', 'support_open', 'referrals', 'referrals_accepted', 'home_requests', 'home_completed', 'tasks_open', 'tasks_overdue'] as $key) {
                if ($summary[$key] !== 2) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_negative_future_and_unobserved_first_responses_do_not_distort_valid_average(): void
    {
        $opened = now()->subHours(2);
        foreach ([$opened->copy()->subMinute(), $opened->copy()->addMinutes(10), $opened->copy()->addMinutes(20), now()->addHour(), now(), null] as $response) {
            $this->support($opened, ['first_response_at' => $response]);
        }
        $this->report()->assertViewHas('summary', fn ($summary) => $summary['avg_first_response_minutes'] === 15);
    }

    public function test_no_valid_response_reports_null_instead_of_zero_or_negative(): void
    {
        $opened = now()->subHour();
        $this->support($opened, ['first_response_at' => $opened->copy()->subMinute()]);
        $this->support($opened, ['first_response_at' => now()->addMinute()]);
        $this->report()->assertViewHas('summary', fn ($summary) => $summary['avg_first_response_minutes'] === null);
    }

    public function test_week_buckets_use_tehran_saturday_boundary_and_include_zero_weeks(): void
    {
        $this->caseAt(Carbon::parse('2026-10-02 20:29:59 UTC'));
        $this->caseAt(Carbon::parse('2026-10-02 20:30:00 UTC'));
        $this->support(Carbon::parse('2026-10-02 20:30:00 UTC'));
        $this->report()->assertViewHas('caseTrend', fn ($trend) => $trend->all() === [
            ['label' => '2026-08-29', 'count' => 0], ['label' => '2026-09-05', 'count' => 0],
            ['label' => '2026-09-12', 'count' => 0], ['label' => '2026-09-19', 'count' => 0],
            ['label' => '2026-09-26', 'count' => 1], ['label' => '2026-10-03', 'count' => 1],
        ])->assertViewHas('supportTrend', fn ($trend) => $trend->sum('count') === 1 && $trend->last() === ['label' => '2026-10-03', 'count' => 1]);
    }

    public function test_exact_saturday_until_does_not_add_empty_non_intersecting_week(): void
    {
        Carbon::setTestNow('2026-10-02 20:30:00 UTC');
        $this->report()->assertViewHas('caseTrend', fn ($trend) => $trend->last() === ['label' => '2026-09-26', 'count' => 0]);
    }

    public static function ranges(): array
    {
        return ['30d' => ['30d', 30], '90d' => ['90d', 90], '1y' => ['1y', 365], 'invalid' => ['PRIVATE-RANGE', 30], 'array' => [['30d'], 30]];
    }

    #[DataProvider('ranges')]
    public function test_range_is_allowlisted_and_report_window_has_explicit_utc_bounds(mixed $range, int $days): void
    {
        $expectedRange = is_string($range) && in_array($range, ['30d', '90d', '1y'], true) ? $range : '30d';
        $url = '/en/panel/analytics?'.http_build_query(['range' => $range]);
        $this->actingAs($this->owner)->get($url)->assertOk()->assertViewHas('range', $expectedRange)->assertViewHas('days', $days)
            ->assertViewHas('reportWindow', ['since' => now()->subDays($days)->toIso8601String(), 'until' => now()->toIso8601String(), 'timezone' => 'Asia/Tehran', 'week_starts_on' => 6])
            ->assertDontSee('PRIVATE-RANGE');
    }

    public function test_current_state_counts_remain_cohort_scoped_and_overdue_excludes_done_or_future_due_tasks(): void
    {
        $case = $this->caseAt(now()->subDay());
        foreach ([['open', now()->subSecond()], ['in_progress', now()->subSecond()], ['open', now()], ['open', now()->addHour()], ['done', now()->subDay()]] as [$status, $due]) {
            CoordinationTask::query()->create(['case_id' => $case->id, 'assignee_user_id' => $this->owner->id, 'task_type' => 'follow_up', 'status' => $status, 'due_at' => $due])->forceFill(['created_at' => now()->subDay()])->save();
        }
        $this->report()->assertViewHas('summary', fn ($summary) => $summary['tasks_open'] === 4 && $summary['tasks_overdue'] === 2);
    }

    public function test_role_inactive_and_demo_denials_retain_owner_only_scope_without_writes(): void
    {
        $before = DB::table('audit_events')->count();
        foreach (['patient', 'coordinator', 'clinician', 'clinic_rep', 'tech_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/en/panel/analytics')->assertForbidden();
        }
        $this->owner->update(['is_active' => false]);
        $this->report()->assertForbidden();
        $this->owner->update(['is_active' => true]);
        $this->actingAs($this->owner)->withSession(['panel_demo' => true])->get('/en/panel/analytics')->assertForbidden();
        $this->assertSame($before, DB::table('audit_events')->count());
    }

    public function test_private_projection_and_queries_never_read_clinical_or_support_free_text(): void
    {
        $this->caseAt(now()->subDay());
        $this->support(now()->subDay(), ['subject' => 'PRIVATE-SUBJECT']);
        $queries = [];
        DB::listen(function ($event) use (&$queries): void {
            $queries[] = $event->sql;
        });
        $response = $this->report()->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $response->assertDontSee('PRIVATE-PATIENT')->assertDontSee('PRIVATE-REASON')->assertDontSee('PRIVATE-SUBJECT')->assertDontSee('09120000001');
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('select * from "patient_cases"', $query);
            $this->assertStringNotContainsString('patient_mobile', $query);
            $this->assertStringNotContainsString('subject', $query);
            $this->assertStringNotContainsString('clinical_documents', $query);
        }
    }

    private function report(): TestResponse
    {
        return $this->actingAs($this->owner)->get('/en/panel/analytics');
    }

    private function caseAt($at): PatientCase
    {
        $case = PatientCase::query()->create(['public_reference' => 'RD-'.strtoupper(Str::random(8)), 'patient_user_id' => $this->patient->id, 'service_type' => 'opg_review', 'status' => 'submitted', 'patient_name' => 'PRIVATE-PATIENT', 'patient_mobile' => '09120000001', 'patient_mobile_hash' => hash('sha256', Str::random()), 'contact_reason' => 'PRIVATE-REASON', 'budget_band' => 'call']);
        $case->forceFill(['created_at' => $at])->save();

        return $case;
    }

    private function support($at, array $overrides = []): SupportConversation
    {
        return SupportConversation::query()->create(['patient_user_id' => $this->patient->id, 'opened_at' => $at, 'status' => 'open', ...$overrides]);
    }
}
