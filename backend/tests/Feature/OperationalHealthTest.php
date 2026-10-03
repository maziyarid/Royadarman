<?php

namespace Tests\Feature;

use App\Domain\Operations\Services\OperationalHealth;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Synthetic data only. NOT RUN when authored: requires independent execution.
 */
class OperationalHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function snapshot(): array
    {
        return $this->app->make(OperationalHealth::class)->snapshot();
    }

    private function job(int $availableAgoSeconds, ?int $reservedAgoSeconds = null): void
    {
        DB::table('jobs')->insert([
            'queue' => 'notifications', 'payload' => '{"secret-payload-marker":true}', 'attempts' => 0,
            'reserved_at' => $reservedAgoSeconds === null ? null : now()->getTimestamp() - $reservedAgoSeconds,
            'available_at' => now()->getTimestamp() - $availableAgoSeconds,
            'created_at' => now()->getTimestamp() - $availableAgoSeconds,
        ]);
    }

    private function outbox(int $availableAgoMinutes, bool $processed = false): string
    {
        return OutboxEvent::query()->create([
            'event_type' => 'case.submitted', 'aggregate_type' => 'synthetic', 'aggregate_id' => 'x',
            'payload' => ['template_key' => 'case_submitted'], 'deduplication_key' => 'dedup-'.Str::random(10),
            'available_at' => now()->subMinutes($availableAgoMinutes),
            'processed_at' => $processed ? now() : null,
        ])->id;
    }

    private function delivery(string $outboxId, string $status, int $createdAgoMinutes, ?int $updatedAgoMinutes = null): void
    {
        DB::table('notification_deliveries')->insert([
            'id' => (string) Str::ulid(), 'outbox_event_id' => $outboxId, 'channel' => 'sms', 'status' => $status,
            'recipient_locale' => 'fa', 'template_key' => 'case_submitted',
            'created_at' => now()->subMinutes($createdAgoMinutes),
            'updated_at' => $updatedAgoMinutes === null ? null : now()->subMinutes($updatedAgoMinutes),
        ]);
    }

    public function test_empty_system_reports_ok_but_worker_liveness_stays_unobservable(): void
    {
        $snapshot = $this->snapshot();

        $this->assertSame('ok', $snapshot['state']);
        $this->assertSame([], $snapshot['reasons']);
        $this->assertSame('unobservable', $snapshot['worker_liveness']);
    }

    public function test_old_unreserved_job_reports_queue_backlog_age(): void
    {
        $this->job(availableAgoSeconds: 600);

        $snapshot = $this->snapshot();

        $this->assertSame('degraded', $snapshot['state']);
        $this->assertContains('queue_backlog_age', $snapshot['reasons']);
        $this->assertSame(600, $snapshot['signals']['oldest_queued_age_seconds']);
    }

    public function test_fresh_job_is_not_a_backlog(): void
    {
        $this->job(availableAgoSeconds: 30);

        $this->assertSame('ok', $this->snapshot()['state']);
    }

    public function test_future_scheduled_job_and_exact_age_boundary_are_not_overdue(): void
    {
        $this->job(availableAgoSeconds: -600);
        $this->job(availableAgoSeconds: 300);

        $this->assertSame('ok', $this->snapshot()['state']);
        $this->job(availableAgoSeconds: 301);
        $this->assertContains('queue_backlog_age', $this->snapshot()['reasons']);
    }

    public function test_queue_observation_respects_configured_database_tables(): void
    {
        Schema::rename('jobs', 'synthetic_jobs');
        Schema::rename('failed_jobs', 'synthetic_failed_jobs');
        config()->set('queue.connections.database.table', 'synthetic_jobs');
        config()->set('queue.failed.table', 'synthetic_failed_jobs');
        DB::table('synthetic_jobs')->insert([
            'queue' => 'notifications', 'payload' => '{}', 'attempts' => 0,
            'reserved_at' => null, 'available_at' => now()->timestamp - 601, 'created_at' => now()->timestamp,
        ]);

        $this->assertContains('queue_backlog_age', $this->snapshot()['reasons']);
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->actingAs($owner)->get('/en/panel/launch-readiness')->assertOk()->assertViewHas('queuedJobs', 1);
    }

    public function test_job_reserved_far_beyond_retry_after_is_reported_as_stale(): void
    {
        $this->job(availableAgoSeconds: 400, reservedAgoSeconds: 400);

        $this->assertContains('stale_reserved_jobs', $this->snapshot()['reasons']);
    }

    public function test_failed_job_degrades_health(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'notifications',
            'payload' => '{}', 'exception' => 'synthetic', 'failed_at' => now(),
        ]);

        $this->assertContains('failed_jobs', $this->snapshot()['reasons']);
    }

    public function test_stuck_outbox_event_is_reported_and_processed_one_is_not(): void
    {
        $this->outbox(availableAgoMinutes: 20, processed: true);
        $this->assertSame('ok', $this->snapshot()['state']);

        $this->outbox(availableAgoMinutes: 20);
        $this->assertContains('stuck_outbox', $this->snapshot()['reasons']);
    }

    public function test_recent_unprocessed_outbox_event_is_pending_but_not_stuck(): void
    {
        $this->outbox(availableAgoMinutes: 2);

        $snapshot = $this->snapshot();

        $this->assertSame(1, $snapshot['signals']['pending_outbox']);
        $this->assertNotContains('stuck_outbox', $snapshot['reasons']);
    }

    public function test_long_running_sending_delivery_and_recent_failed_delivery_are_reported(): void
    {
        $id = $this->outbox(availableAgoMinutes: 1, processed: true);
        $this->delivery($id, 'sending', createdAgoMinutes: 30);
        $this->assertContains('stuck_sending_deliveries', $this->snapshot()['reasons']);

        $second = $this->outbox(availableAgoMinutes: 1, processed: true);
        $this->delivery($second, 'failed', createdAgoMinutes: 60, updatedAgoMinutes: 10);
        $this->assertContains('failed_deliveries_recent', $this->snapshot()['reasons']);
    }

    public function test_old_failed_delivery_is_not_counted_in_recent_failure_telemetry(): void
    {
        $id = $this->outbox(availableAgoMinutes: 1, processed: true);
        $this->delivery($id, 'failed', createdAgoMinutes: 3000, updatedAgoMinutes: 2900);

        $this->assertNotContains('failed_deliveries_recent', $this->snapshot()['reasons']);
    }

    public function test_unresolved_delivery_keeps_readiness_closed_after_24_hours_until_recovered(): void
    {
        $id = $this->outbox(availableAgoMinutes: 1, processed: true);
        $this->delivery($id, 'failed', createdAgoMinutes: 3000, updatedAgoMinutes: 2900);
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $snapshot = $this->snapshot();
        $this->assertSame('degraded', $snapshot['state']);
        $this->assertSame(0, $snapshot['signals']['failed_deliveries_24h']);
        $this->assertSame(1, $snapshot['signals']['failed_deliveries_unresolved']);
        $this->assertContains('failed_deliveries_unresolved', $snapshot['reasons']);
        $this->actingAs($owner)->get('/en/panel/launch-readiness')
            ->assertOk()
            ->assertViewHas('gates', fn (array $gates): bool => $gates['operational_backlog']['ok'] === false)
            ->assertViewHas('ready', false);

        DB::table('notification_deliveries')->where('outbox_event_id', $id)
            ->update(['status' => 'sent', 'updated_at' => now()]);

        $this->assertSame('ok', $this->snapshot()['state']);
        $this->assertSame(0, $this->snapshot()['signals']['failed_deliveries_unresolved']);
    }

    public function test_recent_delivery_activity_does_not_count_as_stalled(): void
    {
        $id = $this->outbox(availableAgoMinutes: 1, processed: true);
        $this->delivery($id, 'sending', createdAgoMinutes: 60, updatedAgoMinutes: 1);

        $this->assertNotContains('stuck_sending_deliveries', $this->snapshot()['reasons']);
    }

    public function test_missing_queue_evidence_is_unknown_and_readiness_stays_viewable(): void
    {
        Schema::drop('jobs');
        $snapshot = $this->snapshot();
        $this->assertSame('unknown', $snapshot['state']);
        $this->assertSame([], $snapshot['signals']);
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $this->actingAs($owner)->get('/en/panel/launch-readiness')
            ->assertOk()->assertViewHas('ready', false)->assertViewHas('queuedJobs', null);
    }

    public function test_only_active_non_demo_operations_roles_can_read_health_in_each_locale(): void
    {
        foreach (['fa', 'ar', 'en'] as $locale) {
            foreach (['owner', 'tech_admin'] as $role) {
                $user = User::factory()->create(['role' => $role, 'is_active' => true]);
                $this->actingAs($user)->get('/'.$locale.'/panel/launch-readiness')
                    ->assertOk()->assertSee(__('panel.launch.worker_unobservable', [], $locale))
                    ->assertDontSee('panel.launch.gates.operational_backlog')
                    ->assertDontSee('panel.launch.gates.integration_settings');
            }
            foreach (['patient', 'coordinator', 'clinician', 'clinic_rep'] as $role) {
                $user = User::factory()->create(['role' => $role, 'is_active' => true]);
                $this->actingAs($user)->get('/'.$locale.'/panel/launch-readiness')->assertForbidden();
            }
        }
        $inactive = User::factory()->create(['role' => 'owner', 'is_active' => false]);
        $this->actingAs($inactive)->get('/en/panel/launch-readiness')->assertForbidden();
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->actingAs($owner)->withSession(['panel_demo' => true])->get('/en/panel/launch-readiness')->assertForbidden();
    }

    public function test_snapshot_is_redacted_counts_only(): void
    {
        $this->job(availableAgoSeconds: 600);
        $id = $this->outbox(availableAgoMinutes: 20);
        $this->delivery($id, 'failed', createdAgoMinutes: 5, updatedAgoMinutes: 5);

        $encoded = json_encode($this->snapshot(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('secret-payload-marker', $encoded);
        $this->assertStringNotContainsString($id, $encoded);
        $this->assertStringNotContainsString('case_submitted', $encoded);
    }

    public function test_launch_readiness_gate_reflects_backlog(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $this->actingAs($owner)->get('/en/panel/launch-readiness')
            ->assertOk()
            ->assertViewHas('gates', fn (array $gates): bool => $gates['operational_backlog']['ok'] === true);

        $this->job(availableAgoSeconds: 900);

        $this->actingAs($owner)->get('/en/panel/launch-readiness')
            ->assertOk()
            ->assertViewHas('gates', fn (array $gates): bool => $gates['operational_backlog']['ok'] === false)
            ->assertViewHas('ready', false);
    }
}
