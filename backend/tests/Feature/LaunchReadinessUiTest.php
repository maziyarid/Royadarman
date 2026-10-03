<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\SessionAssurance;
use App\Models\OutboxEvent;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LaunchReadinessUiTest extends TestCase
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

    public function test_owner_page_uses_external_styles_one_page_heading_and_real_gate_completion_counts(): void
    {
        $response = $this->actingAs($this->account())->get('/en/panel/launch-readiness')->assertOk();
        $response->assertSee('/assets/launch-readiness.css', false)
            ->assertSee('id="launch-gates"', false)
            ->assertSee('id="launch-runtime"', false)
            ->assertSee('id="launch-operations"', false)
            ->assertSee('id="launch-manual"', false)
            ->assertSee('id="launch-policies"', false)
            ->assertDontSee('style=', false)
            ->assertDontSee('onsubmit=', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1>'));
        $response->assertViewHas('gates', function ($gates) use ($response): bool {
            $passed = collect($gates)->filter(fn ($gate) => $gate['ok'] === true)->count();

            return str_contains($response->getContent(), __('launch.gate_completion', ['passed' => $passed, 'total' => count($gates)]));
        });
    }

    public function test_owner_has_four_existing_confirmation_forms_and_technical_administrator_is_read_only(): void
    {
        $owner = $this->account();
        $response = $this->actingAs($owner)->get('/en/panel/launch-readiness')->assertOk();
        $action = 'action="http://localhost/en/panel/launch-readiness/acknowledge"';
        $this->assertSame(4, substr_count($response->getContent(), $action));
        $this->assertSame(4, substr_count($response->getContent(), 'data-launch-acknowledgement'));
        $response->assertSee(__('panel.security.reauthenticate'))
            ->assertSee('data-confirm=', false);

        $this->actingAs($this->account('tech_admin'))->get('/en/panel/launch-readiness')->assertOk()
            ->assertDontSee($action, false)
            ->assertSee(__('launch.owner_only'));
    }

    public function test_other_roles_demo_and_inactive_owner_cannot_view_operational_evidence(): void
    {
        foreach (['patient', 'coordinator', 'clinician', 'clinic_rep'] as $role) {
            $this->actingAs($this->account($role))->get('/en/panel/launch-readiness')->assertForbidden();
        }
        $owner = $this->account();
        $this->actingAs($owner)->withSession(['panel_demo' => true])->get('/en/panel/launch-readiness')->assertForbidden();
        $owner->update(['is_active' => false]);
        $this->actingAs($owner)->withSession(['panel_demo' => false])->get('/en/panel/launch-readiness')->assertForbidden();
    }

    public function test_persisted_operational_counts_are_bound_without_queue_or_delivery_payloads(): void
    {
        foreach ([[601, null], [400, 400]] as [$age, $reserved]) {
            DB::table('jobs')->insert([
                'queue' => 'notifications', 'payload' => 'private-job-payload', 'attempts' => 0,
                'reserved_at' => $reserved === null ? null : now()->timestamp - $reserved,
                'available_at' => now()->timestamp - $age, 'created_at' => now()->timestamp - $age,
            ]);
        }
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'notifications',
            'payload' => 'private-failed-payload', 'exception' => 'private-exception-detail', 'failed_at' => now(),
        ]);
        $outbox = OutboxEvent::query()->create([
            'event_type' => 'case.submitted', 'aggregate_type' => 'synthetic', 'aggregate_id' => 'private-case-reference',
            'payload' => ['private-patient' => 'private-patient-name'], 'deduplication_key' => 'ui-'.Str::ulid(),
            'available_at' => now()->subMinutes(20),
        ]);
        foreach (['failed' => 1800, 'sending' => 20] as $status => $age) {
            $deliveryOutbox = $status === 'failed' ? $outbox : OutboxEvent::query()->create([
                'event_type' => 'case.submitted', 'aggregate_type' => 'synthetic', 'aggregate_id' => 'private-case-reference',
                'payload' => [], 'deduplication_key' => 'ui-'.Str::ulid(), 'available_at' => now()->subMinutes(20), 'processed_at' => now(),
            ]);
            DB::table('notification_deliveries')->insert([
                'id' => (string) Str::ulid(), 'outbox_event_id' => $deliveryOutbox->id, 'channel' => 'sms', 'status' => $status,
                'recipient_locale' => 'fa', 'template_key' => 'case_submitted',
                'created_at' => now()->subMinutes($age), 'updated_at' => now()->subMinutes($age),
            ]);
        }
        $response = $this->actingAs($this->account())->get('/en/panel/launch-readiness')->assertOk();
        foreach (['queued_jobs' => 2, 'failed_jobs' => 1, 'pending_outbox' => 1, 'stuck_outbox' => 1, 'stale_reserved_jobs' => 1, 'stuck_sending_deliveries' => 1, 'failed_deliveries_unresolved' => 1, 'failed_deliveries_24h' => 0] as $signal => $value) {
            $this->assertMatchesRegularExpression('/data-health-signal="'.$signal.'"[\s\S]*?<dd>\s*'.$value.'\s*<\/dd>/', $response->getContent());
        }
        $response->assertSee(__('launch.health_reasons.failed_deliveries_unresolved'))
            ->assertDontSee('panel.launch.health_reasons.failed_deliveries_unresolved')
            ->assertDontSee('private-job-payload')->assertDontSee('private-failed-payload')
            ->assertDontSee('private-exception-detail')->assertDontSee('private-case-reference')
            ->assertDontSee('private-patient-name')->assertDontSee('09123450123');
    }

    public function test_missing_operational_or_heartbeat_evidence_stays_unknown_instead_of_showing_zero_or_recent(): void
    {
        // Missing-data presentation contract, independent of the service runtime tests.
        View::composer('panel.launch-readiness.index', fn ($view) => $view->with([
            'operationalHealth' => ['state' => 'unknown', 'signals' => [], 'reasons' => ['database_unavailable']],
            'runtimeHeartbeat' => null,
        ]));
        $response = $this->actingAs($this->account())->get('/en/panel/launch-readiness')->assertOk();
        $response->assertSee(__('launch.unknown'))
            ->assertSee(__('launch.evidence_states.unobservable'))
            ->assertSee(__('launch.runtime_caveat'));
        $this->assertMatchesRegularExpression('/data-health-signal="queued_jobs"[\s\S]*?<dd>\s*'.preg_quote(__('launch.unknown'), '/').'\s*<\/dd>/', $response->getContent());
        $this->assertSame(5, substr_count($response->getContent(), 'data-runtime-state="unobservable"'));
    }

    public function test_runtime_contract_renders_each_component_state_and_age_without_a_worker_liveness_claim(): void
    {
        // Render contract fixture only; backend tests exercise persisted heartbeats.
        View::composer('panel.launch-readiness.index', fn ($view) => $view->with([
            'runtimeHeartbeat' => [
                'state' => 'degraded', 'observed_at' => '2026-10-03T12:00:00Z',
                'scheduler' => ['state' => 'recent', 'age_seconds' => 0],
                'queues' => [
                    'otp' => ['state' => 'recent', 'age_seconds' => 15],
                    'scanning' => ['state' => 'unobservable', 'age_seconds' => null],
                    'notifications' => ['state' => 'stale', 'age_seconds' => 1200],
                    'maintenance' => ['state' => 'recent', 'age_seconds' => 299],
                ],
            ],
        ]));
        $response = $this->actingAs($this->account())->get('/en/panel/launch-readiness')->assertOk();
        $response->assertSee('data-runtime-component="scheduler" data-runtime-state="recent"', false)
            ->assertSee('data-runtime-component="notifications" data-runtime-state="stale"', false)
            ->assertSee('data-runtime-component="scanning" data-runtime-state="unobservable"', false)
            ->assertSee(__('launch.seconds', ['count' => '1,200']))
            ->assertSee('2026-10-03 15:30:00')
            ->assertSee(__('launch.runtime_caveat'));
    }

    public function test_real_owner_acknowledgement_is_audited_and_shows_timezone_explicit_recorded_time(): void
    {
        $this->actingAs($this->account())->withSession($this->freshSession())
            ->post('/en/panel/launch-readiness/acknowledge', ['gate' => 'legal_approved', 'confirmed' => '1'])
            ->assertRedirect('/en/panel/launch-readiness');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/en/panel/launch-readiness')->assertOk()
            ->assertSee('2026-10-03 15:30:00')
            ->assertSee(__('launch.timezone_hint'));
        $this->assertDatabaseHas('audit_events', ['action' => 'launch_readiness.acknowledgement_changed', 'resource_id' => 'legal_approved']);
    }

    public function test_invalid_acknowledgement_returns_visible_errors_without_a_confirmation_or_audit_write(): void
    {
        $this->actingAs($this->account())->withSession($this->freshSession())->from('/en/panel/launch-readiness')
            ->post('/en/panel/launch-readiness/acknowledge', ['gate' => 'not-a-gate', 'confirmed' => 'invalid'])
            ->assertRedirect('/en/panel/launch-readiness')->assertSessionHasErrors(['gate', 'confirmed']);
        $this->withCookie(config('session.cookie'), session()->getId())->get('/en/panel/launch-readiness')->assertOk()
            ->assertSee('id="launch-validation-errors"', false)
            ->assertSee(__('launch.validation_help'));
        $this->assertDatabaseCount('integration_settings', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_policy_coverage_uses_production_eligible_published_rows_and_an_accessible_table(): void
    {
        foreach ([['en', 'real-v1'], ['fa', 'panel-demo-synthetic']] as [$locale, $version]) {
            PolicyVersion::query()->create([
                'policy_key' => 'case_coordination', 'version' => $version, 'locale' => $locale,
                'content' => 'private-policy-text', 'content_hash' => hash('sha256', 'private-policy-text'), 'published_at' => now(),
            ]);
        }
        $response = $this->actingAs($this->account())->get('/en/panel/launch-readiness')->assertOk();
        $response->assertSee('id="launch-policy-caption"', false)
            ->assertSee('scope="col"', false)->assertSee('scope="row"', false)
            ->assertSee(__('launch.policy_completion', ['published' => 1, 'total' => 9]))
            ->assertDontSee('private-policy-text');
    }

    public function test_injected_detail_and_release_markup_is_escaped_and_invalid_observation_dates_stay_unknown(): void
    {
        $marker = '<script>injected-detail</script>';
        View::composer('panel.launch-readiness.index', fn ($view) => $view->with([
            'gates' => ['unexpected_gate' => ['ok' => false, 'detail' => $marker]],
            'release' => ['commit' => $marker, 'built_at' => $marker],
            'runtimeHeartbeat' => ['state' => 'unknown', 'observed_at' => $marker],
        ]));
        $this->actingAs($this->account())->get('/en/panel/launch-readiness')->assertOk()
            ->assertSee(e($marker), false)->assertDontSee($marker, false)
            ->assertSee(__('launch.unknown_gate'))
            ->assertDontSee('panel.launch.gates.unexpected_gate')
            ->assertDontSee('runtime.gate_title');
    }

    public function test_all_locales_have_localised_signal_and_state_labels_without_raw_translation_keys(): void
    {
        $owner = $this->account();
        foreach (['fa', 'ar', 'en'] as $locale) {
            $this->actingAs($owner)->get('/'.$locale.'/panel/launch-readiness')->assertOk()
                ->assertSee('data-health-signal="failed_deliveries_unresolved"', false)
                ->assertDontSee('launch.health_reasons.')->assertDontSee('launch.signals.')
                ->assertDontSee('launch.evidence_states.')->assertDontSee('runtime.gate_title');
        }
    }

    private function account(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true, 'locale' => 'en']);
    }

    private function freshSession(): array
    {
        return [SessionAssurance::KEY => now()->timestamp, SessionAssurance::METHOD_KEY => 'password'];
    }
}
