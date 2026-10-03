<?php

namespace Tests\Feature;

use App\Domain\Coordination\Services\ReferralLifecycle;
use App\Models\Clinic;
use App\Models\CoordinationTask;
use App\Models\PatientCase;
use App\Models\ReferralProposal;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class OperationsAnalyticsUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-03 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_page_uses_external_assets_one_heading_and_csp_safe_charts(): void
    {
        $response = $this->ownerPage()->assertOk()->assertSee('/assets/analytics-workspace.css', false);
        $html = $response->getContent();
        $this->assertSame(1, preg_match_all('/<h1\b/i', $html));
        $this->assertFileExists(public_path('assets/analytics-workspace.css'));
        $this->assertSame(file_get_contents(public_path('assets/analytics-workspace.css')), file_get_contents(base_path('../deployment/webroot/assets/analytics-workspace.css')));
        $this->assertDoesNotMatchRegularExpression('/\s(?:style|on[a-z]+)=/i', $html);
        foreach (['analytics-overview', 'analytics-distributions', 'analytics-trends', 'analytics-outcomes'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
        $response->assertSee(__('analytics.cohort_help'));
    }

    public function test_range_links_select_only_the_real_current_range(): void
    {
        foreach (['30d', '90d', '1y'] as $range) {
            $html = $this->ownerPage('en', $range)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'aria-current="page"'));
            $this->assertMatchesRegularExpression('/<a[^>]+href="[^"]*range='.$range.'"[^>]+aria-current="page"/', $html);
        }
    }

    public function test_persisted_counts_are_visible_without_sensitive_drilldowns(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'name' => 'PRIVATE-PATIENT']);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $case = PatientCase::query()->create([
            'public_reference' => 'PRIVATE-REFERENCE', 'patient_user_id' => $patient->id,
            'service_type' => 'guidance_referral', 'status' => 'submitted', 'patient_name' => $patient->name,
            'patient_mobile' => '09123456789', 'patient_mobile_hash' => hash('sha256', '09123456789'),
            'budget_band' => 'unspecified', 'contact_reason' => 'PRIVATE-CONTENT',
        ]);
        $case->forceFill(['created_at' => now()->subMinutes(20)])->save();
        SupportConversation::query()->create([
            'patient_user_id' => $patient->id, 'case_id' => $case->id, 'subject' => 'PRIVATE-SUBJECT',
            'opened_at' => now()->subMinutes(10), 'first_response_at' => now()->subMinutes(5),
        ]);
        $task = CoordinationTask::query()->create([
            'case_id' => $case->id, 'assignee_user_id' => $coordinator->id,
            'task_type' => 'follow_up', 'status' => 'open', 'due_at' => now()->subMinute(),
            'operational_note' => 'PRIVATE-NOTE',
        ]);
        $task->forceFill(['created_at' => now()->subMinutes(15)])->save();
        $response = $this->ownerPage()->assertOk();
        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['cases']);
        $this->assertSame(1, $summary['support_open']);
        $this->assertSame(1, $summary['tasks_open']);
        $this->assertSame(5, $summary['avg_first_response_minutes']);
        $response->assertSee('data-metric="cases"', false)->assertSee('data-metric="support_open"', false)
            ->assertSee('data-metric="tasks_overdue"', false)->assertSee('<progress', false);
        foreach (['PRIVATE-PATIENT', 'PRIVATE-REFERENCE', 'PRIVATE-CONTENT', 'PRIVATE-SUBJECT', 'PRIVATE-NOTE', '09123456789'] as $private) {
            $response->assertDontSee($private);
        }
        $response->assertSee(__('analytics.status_help'))->assertSee(__('analytics.response_help'));
    }

    public function test_no_response_is_unknown_and_empty_data_is_not_fake_success(): void
    {
        $response = $this->ownerPage()->assertOk();
        $this->assertNull($response->viewData('summary')['avg_first_response_minutes']);
        $response->assertSee(__('analytics.no_response'))->assertSee(__('analytics.empty'));
        $this->assertDoesNotMatchRegularExpression('/data-metric="avg_first_response_minutes"[^>]*>\s*0\s*</', $response->getContent());
    }

    public function test_supplied_report_window_and_zero_week_projection_render(): void
    {
        $data = $this->ownerPage()->viewData();
        $data['reportWindow'] = ['since' => '2026-09-03T12:00:00+00:00', 'until' => '2026-10-03T12:00:00+00:00', 'timezone' => 'Asia/Tehran', 'week_starts_on' => 6];
        $data['caseTrend'] = collect([['label' => '2026-09-05', 'count' => 0], ['label' => '2026-09-12', 'count' => 3]]);
        $html = view('panel.analytics.index', $data)->render();
        foreach (['datetime="2026-09-03T12:00:00+00:00"', '2026-09-03 15:30', '2026-10-03 15:30', '2026-09-05', 'value="0"', 'max="3"', __('analytics.week_help')] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
    }

    public function test_zero_filled_weeks_remain_visible_with_an_explicit_no_activity_notice(): void
    {
        $data = $this->ownerPage()->viewData();
        $data['caseTrend'] = collect([['label' => '2026-09-05', 'count' => 0], ['label' => '2026-09-12', 'count' => 0]]);
        $data['supportTrend'] = collect();
        $html = view('panel.analytics.index', $data)->render();
        $this->assertStringContainsString(__('analytics.all_zero'), $html);
        $this->assertStringContainsString('2026-09-05', $html);
        $this->assertStringContainsString('2026-09-12', $html);
        $this->assertSame(2, substr_count($html, 'value="0"'));
        $this->assertSame(2, substr_count($html, 'max="1"'));
    }

    public function test_invalid_projection_stays_unknown_and_never_echoes_untrusted_category(): void
    {
        $data = $this->ownerPage()->viewData();
        $data['reportWindow'] = ['since' => '<script>bad()</script>', 'until' => 'not-a-date'];
        $data['summary']['cases'] = null;
        $data['caseStatuses'] = collect(['<script>bad()</script>' => 2]);
        $html = view('panel.analytics.index', $data)->render();
        $this->assertStringNotContainsString('<script>bad()</script>', $html);
        $this->assertStringContainsString(__('analytics.unavailable'), $html);
        $this->assertStringContainsString(__('analytics.unknown_category'), $html);
        $this->assertStringNotContainsString('datetime="not-a-date"', $html);
    }

    public function test_referral_categories_bind_real_decisions_withdrawal_and_recorded_sla_evidence(): void
    {
        $until = now()->toImmutable();
        $owner = User::factory()->create(['role' => 'owner']);
        $clinic = Clinic::query()->create(['name' => 'Synthetic clinic', 'city' => 'tehran', 'is_active' => true]);
        $case = PatientCase::query()->create([
            'public_reference' => 'UI-REFERRAL-FIXTURE', 'service_type' => 'guidance_referral', 'status' => 'submitted',
            'patient_mobile' => '09123456789', 'patient_mobile_hash' => hash('sha256', 'ui-referral-fixture'), 'budget_band' => 'call',
        ]);
        $make = fn (string $status = 'proposed') => ReferralProposal::query()->create([
            'case_id' => $case->id, 'clinic_id' => $clinic->id, 'proposed_by_user_id' => $owner->id,
            'status' => $status, 'reasoning' => 'PRIVATE-REFERRAL-FIXTURE', 'source_language' => 'en',
            'proposed_at' => $until->subDays(2),
        ]);
        $make();
        $make('accepted');
        $make('declined');
        $make('rejected'); // Deliberately unsupported raw input must map to unknown, not an invented legacy status.
        $withdrawn = $make();
        $expired = $make();
        $silent = $make();
        Carbon::setTestNow($until->subSecond());
        $lifecycle = app(ReferralLifecycle::class);
        $lifecycle->overrideWithdraw($withdrawn, $owner, 'synthetic withdrawal');
        $lifecycle->recordViewed($expired, $owner);
        $this->assertNotNull($lifecycle->surfaceExpiry($expired));
        $this->assertNotNull($lifecycle->surfaceExpiry($silent));
        Carbon::setTestNow($until);
        $eventCount = DB::table('referral_lifecycle_events')->count();
        foreach (['fa', 'ar', 'en'] as $locale) {
            $response = $this->ownerPage($locale)->assertOk();
            $counts = $response->viewData('referralStatuses');
            $this->assertEquals(array_fill_keys(['proposed', 'accepted', 'declined', 'withdrawn', 'expired', 'silent_loss', 'unknown'], 1), $counts->all());
            $html = $response->getContent();
            foreach (['proposed', 'accepted', 'declined', 'withdrawn', 'expired', 'silent_loss'] as $category) {
                $this->assertStringContainsString(e(__('analytics.referral_status.'.$category)), $html);
                $this->assertStringNotContainsString('analytics.referral_status.'.$category, $html);
            }
            $this->assertStringContainsString(e(__('analytics.referral_help')), $html);
            $this->assertStringNotContainsString('analytics.referral_help', $html);
            $this->assertStringContainsString(e(__('analytics.unknown_category')), $html);
            $this->assertStringNotContainsString('analytics.referral_status.rejected', $html);
            $this->assertStringNotContainsString('PRIVATE-REFERRAL-FIXTURE', $html);
            $this->assertSame($eventCount, DB::table('referral_lifecycle_events')->count());
        }
    }

    public function test_owner_policy_remains_strict_for_other_roles_inactive_and_demo(): void
    {
        foreach (['patient', 'coordinator', 'clinician', 'clinic_rep', 'tech_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/en/panel/analytics')->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => false]))->get('/en/panel/analytics')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'owner']))->withSession(['panel_demo' => true])->get('/en/panel/analytics')->assertForbidden();
    }

    public function test_all_locales_render_copy_without_raw_translation_keys(): void
    {
        foreach (['fa', 'ar', 'en'] as $locale) {
            $html = $this->ownerPage($locale)->assertOk()->getContent();
            $this->assertStringContainsString(__('analytics.cohort_help'), $html);
            $this->assertStringNotContainsString('analytics.cohort_help', $html);
            $this->assertStringNotContainsString('analytics.no_response', $html);
        }
    }

    private function ownerPage(string $locale = 'en', string $range = '30d'): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/'.$locale.'/panel/analytics?range='.$range);
    }
}
