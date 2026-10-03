<?php

namespace Tests\Feature;

use App\Domain\Operations\Services\OperationalHeartbeats;
use App\Jobs\RecordOperationalHeartbeat;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class OperationalHeartbeatsTest extends TestCase
{
    private string $directory;

    private OperationalHeartbeats $heartbeats;

    protected function setUp(): void
    {
        parent::setUp();
        // Actual SQL commit/rollback boundaries are the subject of these tests.
        // Use fresh synthetic schema without an enclosing RefreshDatabase transaction.
        $this->artisan('migrate:fresh')->assertSuccessful();
        RefreshDatabaseState::$migrated = false;
        Carbon::setTestNow('2026-10-03 12:00:00 UTC');
        $this->directory = sys_get_temp_dir().'/roya-heartbeat-test-'.bin2hex(random_bytes(10));
        $this->heartbeats = new OperationalHeartbeats($this->directory);
        $this->heartbeats->activate();
        $this->app->instance(OperationalHeartbeats::class, $this->heartbeats);
        $this->app->useStoragePath($this->directory.'/test-storage');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        Carbon::setTestNow();
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_missing_evidence_closes_runtime_gate_and_is_private_for_authorized_viewer(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/en/panel/launch-readiness')->assertOk()
            ->assertViewHas('gates', fn (array $gates) => isset($gates['runtime_heartbeat']) && $gates['runtime_heartbeat']['ok'] === false)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('runtimeHeartbeat', fn (array $evidence) => $evidence['state'] === 'unknown')
            ->assertViewHas('ready', false);
    }

    public function test_scheduler_registers_minute_probe_command_without_overlapping(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'operations:heartbeat'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_probes_are_inactive_by_default_until_explicit_operator_activation(): void
    {
        unlink($this->directory.'/activation.json');
        $this->artisan('operations:heartbeat')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
        $this->assertFileDoesNotExist($this->directory.'/scheduler.json');
        $this->artisan('operations:heartbeat', ['--activate' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(0600, fileperms($this->directory.'/activation.json') & 0777);
        $this->artisan('operations:heartbeat')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 4);
    }

    public function test_retirement_disables_producer_and_deletes_only_unreserved_own_probes(): void
    {
        $this->heartbeats->tick();
        $this->consume('otp');
        $foreign = DB::table('jobs')->insertGetId([
            'queue' => 'maintenance', 'payload' => '{"unrelated":true}', 'attempts' => 0,
            'reserved_at' => null, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp,
        ]);
        $this->artisan('operations:heartbeat', ['--retire' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('jobs', ['id' => $foreign, 'payload' => '{"unrelated":true}']);
        $this->assertFileExists($this->directory.'/otp.json');
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
        $this->artisan('operations:heartbeat')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_reserved_probe_blocks_all_retirement_deletions_but_producer_stays_disabled(): void
    {
        $this->heartbeats->tick();
        $reserved = Queue::connection('database')->pop('maintenance');
        $this->artisan('operations:heartbeat', ['--retire' => true])->assertFailed();
        $this->assertDatabaseCount('jobs', 4);
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
        $this->artisan('operations:heartbeat')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 4);
        $reserved->fire();
        $reserved->delete();
        $this->artisan('operations:heartbeat', ['--retire' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_foreign_pending_pointer_rolls_back_retirement_and_never_deletes_foreign_or_earlier_own_row(): void
    {
        $this->heartbeats->tick();
        $pending = json_decode(file_get_contents($this->directory.'/pending-maintenance.json'), true);
        DB::table('jobs')->where('id', $pending['job_id'])->update(['payload' => '{"unrelated":true}']);
        $this->artisan('operations:heartbeat', ['--retire' => true])->assertFailed();
        $this->assertDatabaseCount('jobs', 4);
        $this->assertDatabaseHas('jobs', ['id' => $pending['job_id'], 'payload' => '{"unrelated":true}']);
        $this->assertFalse(json_decode(file_get_contents($this->directory.'/activation.json'), true)['active']);
        $this->artisan('operations:heartbeat')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 4);
    }

    public function test_sql_rollback_after_pending_publication_recovers_prior_probe_before_retirement(): void
    {
        $this->heartbeats->tick();
        $priorIds = DB::table('jobs')->orderBy('id')->pluck('id')->all();
        Carbon::setTestNow(now()->addSeconds(301));
        $dispatcher = DB::connection()->getEventDispatcher();
        $triggered = false;
        $dispatcher->listen(TransactionCommitting::class, function ($event) use (&$triggered): void {
            if (! $triggered && is_file($this->directory.'/journal-otp.json')
                && json_decode(file_get_contents($this->directory.'/journal-otp.json'), true)['next'] !== null) {
                $triggered = true;
                $event->connection->getPdo()->rollBack();
                throw new RuntimeException('Synthetic SQL commit failure after journal publication.');
            }
        });
        try {
            $this->artisan('operations:heartbeat')->assertFailed();
            $this->assertTrue($triggered);
        } finally {
            $dispatcher->forget(TransactionCommitting::class);
        }
        $this->assertSame($priorIds, DB::table('jobs')->orderBy('id')->pluck('id')->all());

        $this->artisan('operations:heartbeat', ['--retire' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_symlink_or_malformed_activation_fails_closed_and_never_overwrites_link_target(): void
    {
        $this->write('activation', '{"version":1,"active":"yes"}');
        $this->artisan('operations:heartbeat')->assertFailed();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
        unlink($this->directory.'/activation.json');
        file_put_contents($this->directory.'/outside', 'do-not-change');
        symlink($this->directory.'/outside', $this->directory.'/activation.json');
        $this->artisan('operations:heartbeat', ['--activate' => true])->assertFailed();
        $this->artisan('operations:heartbeat', ['--retire' => true])->assertFailed();
        $this->assertSame('do-not-change', file_get_contents($this->directory.'/outside'));
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
    }

    public function test_command_persists_exactly_four_database_probes_even_with_sync_default_and_does_not_flood(): void
    {
        $this->assertSame('sync', config('queue.default'));
        $this->artisan('operations:heartbeat')->assertSuccessful();
        $this->assertSame('recent', $this->heartbeats->snapshot()['scheduler']['state']);
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
        $this->assertDatabaseCount('jobs', 4);
        $this->assertSame(OperationalHeartbeats::QUEUES, DB::table('jobs')->orderBy('id')->pluck('queue')->all());
        $this->artisan('operations:heartbeat')->assertSuccessful();
        Carbon::setTestNow(now()->addSeconds(300));
        $this->artisan('operations:heartbeat')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 4);
        $this->assertSame(0700, fileperms($this->directory) & 0777);
        foreach (glob($this->directory.'/*') as $file) {
            $this->assertSame(0600, fileperms($file) & 0777);
        }
        $this->assertSame(0600, fileperms($this->directory.'/.lock') & 0777);
    }

    public function test_actual_reserved_queue_jobs_record_all_signals_and_positive_readiness_gate(): void
    {
        $this->heartbeats->tick();
        foreach (OperationalHeartbeats::QUEUES as $queue) {
            $this->consume($queue);
        }
        $snapshot = $this->heartbeats->snapshot();
        $this->assertSame('ok', $snapshot['state']);
        $this->assertSame(OperationalHeartbeats::QUEUES, array_keys($snapshot['queues']));
        $this->assertSame(['state' => 'recent', 'age_seconds' => 0], $snapshot['scheduler']);
        $this->assertSame('2026-10-03T12:00:00+00:00', $snapshot['observed_at']);
        foreach ($snapshot['queues'] as $row) {
            $this->assertSame(['state' => 'recent', 'age_seconds' => 0], $row);
        }
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/en/panel/launch-readiness')->assertOk()
            ->assertViewHas('gates', fn (array $gates) => $gates['runtime_heartbeat']['ok'] === true);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_delayed_probe_preserves_original_issued_age_and_stale_replay_does_not_renew(): void
    {
        $this->heartbeats->tick();
        Carbon::setTestNow(now()->addSeconds(299));
        $this->consume('otp');
        $this->assertSame(['state' => 'recent', 'age_seconds' => 299], $this->heartbeats->snapshot()['queues']['otp']);
        Carbon::setTestNow(now()->addSeconds(2));
        $this->consume('scanning');
        $this->assertSame(['state' => 'unobservable', 'age_seconds' => null], $this->heartbeats->snapshot()['queues']['scanning']);
        $this->assertSame(['state' => 'stale', 'age_seconds' => 301], $this->heartbeats->snapshot()['queues']['otp']);
    }

    public function test_expired_probes_are_replaced_without_deleting_foreign_or_reserved_jobs(): void
    {
        $this->heartbeats->tick();
        $foreign = DB::table('jobs')->insertGetId([
            'queue' => 'otp', 'payload' => '{"synthetic-unrelated-job":true}', 'attempts' => 0,
            'reserved_at' => null, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp,
        ]);
        $reserved = Queue::connection('database')->pop('scanning');
        $this->assertNotNull($reserved);
        Carbon::setTestNow(now()->addSeconds(301));
        $this->heartbeats->tick();
        $this->assertDatabaseCount('jobs', 5);
        $this->assertDatabaseHas('jobs', ['id' => $foreign, 'payload' => '{"synthetic-unrelated-job":true}']);
        $this->assertDatabaseHas('jobs', ['id' => $reserved->getJobId(), 'attempts' => 1]);
        $reserved->fire();
        $reserved->delete();
        $this->assertSame('unobservable', $this->heartbeats->snapshot()['queues']['scanning']['state']);
    }

    public function test_forged_pending_pointer_cannot_delete_an_unrelated_job(): void
    {
        $this->heartbeats->tick();
        $row = DB::table('jobs')->where('queue', 'otp')->first();
        DB::table('jobs')->where('id', $row->id)->update(['payload' => '{"foreign":true}']);
        Carbon::setTestNow(now()->addSeconds(301));
        $this->artisan('operations:heartbeat')->assertFailed();
        $this->assertDatabaseHas('jobs', ['id' => $row->id, 'payload' => '{"foreign":true}']);
        $this->assertDatabaseCount('jobs', 4);
        $this->assertSame('stale', $this->heartbeats->snapshot()['scheduler']['state']);
    }

    public function test_older_replayed_probe_cannot_overwrite_newer_evidence(): void
    {
        $this->heartbeats->tick();
        $first = json_decode(file_get_contents($this->directory.'/pending-otp.json'), true);
        $this->consume('otp');
        Carbon::setTestNow(now()->addSeconds(301));
        $this->heartbeats->tick();
        $this->consume('otp');
        (new RecordOperationalHeartbeat('otp', $first['issued_at'], $first['probe_id']))->handle($this->heartbeats);
        $this->assertSame(['state' => 'recent', 'age_seconds' => 0], $this->heartbeats->snapshot()['queues']['otp']);
    }

    public function test_consumed_probes_are_renewed_each_tick_without_healthy_age_boundary_flicker(): void
    {
        $this->heartbeats->tick();
        foreach (OperationalHeartbeats::QUEUES as $queue) {
            $this->consume($queue);
        }
        foreach ([60, 120, 180, 240, 299, 300, 301, 360] as $elapsed) {
            Carbon::setTestNow(Carbon::parse('2026-10-03 12:00:00 UTC')->addSeconds($elapsed));
            $this->heartbeats->tick();
            foreach (OperationalHeartbeats::QUEUES as $queue) {
                $this->consume($queue);
            }
            $this->assertSame('ok', $this->heartbeats->snapshot()['state']);
            $this->assertSame(0, $this->heartbeats->snapshot()['queues']['otp']['age_seconds']);
            $this->assertDatabaseCount('jobs', 0);
        }
    }

    public function test_bounded_lock_contention_can_retry_a_reserved_probe_without_failed_jobs(): void
    {
        $this->heartbeats->tick();
        $job = Queue::connection('database')->pop('otp');
        $this->assertSame(3, $job->maxTries());
        $lock = fopen($this->directory.'/.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $started = microtime(true);
            try {
                $job->fire();
                $this->fail('Expected bounded contention failure.');
            } catch (RuntimeException) {
                $this->assertLessThan(2, microtime(true) - $started);
                $job->release(1);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        Carbon::setTestNow(now()->addSeconds(2));
        $this->consume('otp', 2);
        $this->assertSame(['state' => 'recent', 'age_seconds' => 2], $this->heartbeats->snapshot()['queues']['otp']);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public static function invalidEvidence(): array
    {
        return [
            'corrupt' => ['{'], 'future' => ['{"version":1,"issued_at":1791028801}'],
            'string timestamp' => ['{"version":1,"issued_at":"1791028800"}'],
            'extra sensitive field' => ['{"version":1,"issued_at":1791028800,"secret":"do-not-project"}'],
            'too large' => [str_repeat('x', 4097)], 'list' => ['[1,2]'],
        ];
    }

    #[DataProvider('invalidEvidence')]
    public function test_malformed_or_future_file_is_unobservable_without_exposing_its_content(string $json): void
    {
        $this->write('scheduler', $json);
        $snapshot = $this->heartbeats->snapshot();
        $this->assertSame('unknown', $snapshot['state']);
        $this->assertSame(['state' => 'unobservable', 'age_seconds' => null], $snapshot['scheduler']);
        $this->assertStringNotContainsString('do-not-project', json_encode($snapshot));
        $this->assertStringNotContainsString($this->directory, json_encode($snapshot));
    }

    public function test_recent_boundary_then_stale_aggregate_and_monotonic_scheduler(): void
    {
        $this->heartbeats->tick();
        foreach (OperationalHeartbeats::QUEUES as $queue) {
            $this->consume($queue);
        }
        Carbon::setTestNow(now()->addSeconds(300));
        $this->assertSame('ok', $this->heartbeats->snapshot()['state']);
        Carbon::setTestNow(now()->addSecond());
        $this->assertSame('degraded', $this->heartbeats->snapshot()['state']);
        $this->heartbeats->tick();
        $new = file_get_contents($this->directory.'/scheduler.json');
        Carbon::setTestNow(now()->subSecond());
        $this->artisan('operations:heartbeat')->assertFailed();
        $this->assertSame($new, file_get_contents($this->directory.'/scheduler.json'));
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
    }

    public function test_symlink_and_storage_failure_fail_closed_without_overwriting_other_files(): void
    {
        $this->heartbeats->snapshot();
        $outside = $this->directory.'/outside';
        file_put_contents($outside, 'synthetic-do-not-change');
        symlink($outside, $this->directory.'/scheduler.json');
        $this->artisan('operations:heartbeat')->assertFailed();
        $this->assertSame('synthetic-do-not-change', file_get_contents($outside));
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
        unlink($this->directory.'/scheduler.json');
        $broken = new OperationalHeartbeats($outside.'/nested');
        $this->app->instance(OperationalHeartbeats::class, $broken);
        Log::shouldReceive('warning')->once()->with('Operational heartbeat could not be recorded.', ['exception_class' => RuntimeException::class]);
        $this->artisan('operations:heartbeat')->assertFailed();
        $this->assertSame('unknown', $broken->snapshot()['state']);
    }

    public function test_database_failure_returns_nonzero_without_fresh_scheduler_or_queue_evidence(): void
    {
        Schema::drop('jobs');
        $this->artisan('operations:heartbeat')->assertFailed();
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
        $this->assertFileDoesNotExist($this->directory.'/scheduler.json');
    }

    public function test_storage_failure_after_queue_push_rolls_back_probe_without_fresh_scheduler(): void
    {
        $directory = $this->directory;
        Queue::createPayloadUsing(function (string $connection, string $queue, array $payload) use ($directory): array {
            unlink($directory.'/journal-'.$queue.'.json');
            mkdir($directory.'/journal-'.$queue.'.json');

            return [];
        });
        try {
            $this->artisan('operations:heartbeat')->assertFailed();
            $this->assertDatabaseCount('jobs', 0);
            $this->assertFileDoesNotExist($this->directory.'/scheduler.json');
            $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
        } finally {
            Queue::createPayloadUsing(null);
        }
    }

    public function test_committed_probe_recovers_after_pending_file_publication_failure(): void
    {
        $directory = $this->directory;
        Queue::createPayloadUsing(function (string $connection, string $queue, array $payload) use ($directory): array {
            mkdir($directory.'/pending-'.$queue.'.json');

            return [];
        });
        try {
            $this->artisan('operations:heartbeat')->assertFailed();
            $this->assertDatabaseCount('jobs', 1);
            $this->assertFileExists($this->directory.'/journal-otp.json');
        } finally {
            Queue::createPayloadUsing(null);
        }
        rmdir($directory.'/pending-otp.json');
        $this->artisan('operations:heartbeat')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 4);
        $this->artisan('operations:heartbeat', ['--retire' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_ambiguous_two_live_journal_identities_are_preserved_and_retirement_refused(): void
    {
        $this->heartbeats->tick();
        $next = json_decode(file_get_contents($this->directory.'/pending-otp.json'), true);
        $payload = DB::table('jobs')->where('id', $next['job_id'])->value('payload');
        $prior = $next;
        $prior['job_id'] = DB::table('jobs')->insertGetId([
            'queue' => 'otp', 'payload' => $payload, 'attempts' => 0, 'reserved_at' => null,
            'available_at' => now()->timestamp, 'created_at' => now()->timestamp,
        ]);
        $this->write('journal-otp', json_encode(['version' => 1, 'prior' => $prior, 'next' => $next]));
        $this->artisan('operations:heartbeat', ['--retire' => true])->assertFailed();
        $this->assertDatabaseCount('jobs', 5);
        $this->assertFileExists($this->directory.'/journal-otp.json');
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
    }

    public function test_nested_transaction_is_rejected_before_dispatch_or_cleanup_mutation(): void
    {
        DB::beginTransaction();
        try {
            $this->artisan('operations:heartbeat')->assertFailed();
            $this->assertDatabaseCount('jobs', 0);
        } finally {
            DB::rollBack();
        }
    }

    public function test_failed_next_tick_preserves_historical_evidence_but_database_readiness_stays_closed(): void
    {
        $this->heartbeats->tick();
        foreach (OperationalHeartbeats::QUEUES as $queue) {
            $this->consume($queue);
        }
        $this->assertSame('ok', $this->heartbeats->snapshot()['state']);
        $previous = file_get_contents($this->directory.'/scheduler.json');
        Schema::drop('jobs');
        $this->artisan('operations:heartbeat')->assertFailed();
        $this->assertSame($previous, file_get_contents($this->directory.'/scheduler.json'));
        $this->assertSame('ok', $this->heartbeats->snapshot()['state']);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get('/en/panel/launch-readiness')->assertOk()->assertViewHas('ready', false)
            ->assertViewHas('gates', fn (array $gates) => $gates['operational_backlog']['ok'] === false);
        Carbon::setTestNow(now()->addSeconds(301));
        $this->assertSame('degraded', $this->heartbeats->snapshot()['state']);
    }

    public function test_missing_persisted_row_recovers_next_tick_without_waiting_ttl(): void
    {
        $this->heartbeats->tick();
        DB::table('jobs')->where('queue', 'otp')->delete();
        Carbon::setTestNow(now()->addSecond());
        $this->heartbeats->tick();
        $this->assertDatabaseCount('jobs', 4);
        $pending = json_decode(file_get_contents($this->directory.'/pending-otp.json'), true);
        $this->assertSame(now()->timestamp, $pending['issued_at']);
        $this->consume('otp');
        $this->assertSame(['state' => 'recent', 'age_seconds' => 0], $this->heartbeats->snapshot()['queues']['otp']);
    }

    public function test_private_record_permissions_and_future_scheduler_do_not_get_promoted(): void
    {
        $this->write('scheduler', json_encode(['version' => 1, 'issued_at' => now()->timestamp + 1]));
        $this->artisan('operations:heartbeat')->assertFailed();
        $this->assertSame('unknown', $this->heartbeats->snapshot()['state']);
        $this->write('scheduler', json_encode(['version' => 1, 'issued_at' => now()->timestamp]));
        chmod($this->directory.'/scheduler.json', 0644);
        clearstatcache();
        $this->assertSame('unobservable', $this->heartbeats->snapshot()['scheduler']['state']);
        $this->artisan('operations:heartbeat')->assertFailed();
    }

    public function test_heartbeat_projection_is_exact_redacted_allowlist(): void
    {
        $this->heartbeats->tick();
        foreach (OperationalHeartbeats::QUEUES as $queue) {
            $this->consume($queue);
        }
        $snapshot = $this->heartbeats->snapshot();
        $this->assertSame(['state', 'scheduler', 'queues', 'observed_at'], array_keys($snapshot));
        $encoded = json_encode($snapshot);
        foreach (['job_id', 'probe_id', 'payload', 'exception', $this->directory] as $private) {
            $this->assertStringNotContainsString($private, $encoded);
        }
    }

    public function test_release_identity_projection_is_bounded_and_discards_non_display_fields(): void
    {
        mkdir(storage_path('app'), 0700, true);
        $path = storage_path('app/release-identity.json');
        $owner = User::factory()->create(['role' => 'owner']);
        file_put_contents($path, json_encode(['commit' => 'synthetic-test-release', 'built_at' => '2026-10-03T12:00:00+00:00', 'secret' => 'do-not-expose']));
        $this->actingAs($owner)->get('/en/panel/launch-readiness')->assertOk()
            ->assertViewHas('release', ['commit' => 'synthetic-test-release', 'built_at' => '2026-10-03T12:00:00+00:00'])
            ->assertDontSee('do-not-expose');
        foreach (['{', str_repeat('x', 4097), '{"commit":[],"built_at":"invalid"}', '{"commit":"release","built_at":"2026-02-31T12:00:00+00:00"}'] as $bad) {
            file_put_contents($path, $bad);
            $this->actingAs($owner)->get('/en/panel/launch-readiness')->assertOk()->assertViewHas('release', null);
        }
    }

    public function test_only_active_non_demo_operations_roles_can_view_evidence(): void
    {
        foreach (['fa', 'ar', 'en'] as $locale) {
            foreach (['owner', 'tech_admin'] as $role) {
                $this->actingAs(User::factory()->create(['role' => $role]))->get('/'.$locale.'/panel/launch-readiness')->assertOk()
                    ->assertHeader('Cache-Control', 'no-store, private');
            }
            foreach (['patient', 'coordinator', 'clinician', 'clinic_rep'] as $role) {
                $this->actingAs(User::factory()->create(['role' => $role]))->get('/'.$locale.'/panel/launch-readiness')->assertForbidden();
            }
        }
        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => false]))->get('/en/panel/launch-readiness')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'owner']))->withSession(['panel_demo' => true])->get('/en/panel/launch-readiness')->assertForbidden();
    }

    private function consume(string $queue, int $attempts = 1): void
    {
        $job = Queue::connection('database')->pop($queue);
        $this->assertNotNull($job);
        $this->assertDatabaseHas('jobs', ['id' => $job->getJobId(), 'attempts' => $attempts]);
        $job->fire();
        $job->delete();
    }

    private function write(string $name, string $json): void
    {
        $this->heartbeats->snapshot();
        file_put_contents($this->directory.'/'.$name.'.json', $json);
        chmod($this->directory.'/'.$name.'.json', 0600);
    }
}
