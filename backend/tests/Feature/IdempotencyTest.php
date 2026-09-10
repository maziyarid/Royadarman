<?php

namespace Tests\Feature;

use App\Domain\Operations\Services\Idempotency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_call_executes_callback_and_records_result(): void
    {
        $user = User::factory()->create();
        $result = $this->idempotency()->execute($user, 'op.test', 'key-1', ['x' => 1], fn () => ['status' => 201, 'body' => ['data' => 'created']]);

        $this->assertSame(201, $result['status']);
        $this->assertSame('created', $result['body']['data']);
        $this->assertDatabaseHas('idempotency_records', ['operation' => 'op.test', 'idempotency_key' => 'key-1', 'response_status' => 201]);
    }

    public function test_replay_with_same_key_returns_cached_result_without_re_executing(): void
    {
        $user = User::factory()->create();
        $calls = 0;
        $callback = function () use (&$calls): array {
            $calls++;

            return ['status' => 200, 'body' => ['n' => $calls]];
        };
        $service = $this->idempotency();
        $first = $service->execute($user, 'op.test', 'key-2', ['x' => 1], $callback);
        $second = $service->execute($user, 'op.test', 'key-2', ['x' => 1], $callback);

        $this->assertSame(1, $calls);
        $this->assertSame($first['body']['n'], $second['body']['n']);
        $this->assertSame(1, $second['body']['n']);
    }

    public function test_reuse_with_different_payload_aborts_conflict(): void
    {
        $user = User::factory()->create();
        $service = $this->idempotency();
        $service->execute($user, 'op.test', 'key-3', ['x' => 1], fn () => ['status' => 201, 'body' => []]);

        $this->expectExceptionMessage('Idempotency key reused');
        $service->execute($user, 'op.test', 'key-3', ['x' => 999], fn () => ['status' => 201, 'body' => []]);
    }

    public function test_empty_or_oversized_key_is_rejected(): void
    {
        $user = User::factory()->create();
        $service = $this->idempotency();
        try {
            $service->execute($user, 'op.test', '', [], fn () => ['status' => 200, 'body' => []]);
            $this->fail('Expected validation exception for empty key.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
        try {
            $service->execute($user, 'op.test', Str::random(101), [], fn () => ['status' => 200, 'body' => []]);
            $this->fail('Expected validation exception for oversized key.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
    }

    public function test_concurrent_insert_with_same_key_resolves_to_single_result_not_sql_500(): void
    {
        $user = User::factory()->create();
        $key = 'race-key-'.Str::uuid();
        $hash = hash('sha256', json_encode(['x' => 1], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $id = (string) Str::ulid();
        DB::table('idempotency_records')->insert([
            'id' => $id,
            'actor_user_id' => $user->id,
            'operation' => 'op.test',
            'idempotency_key' => $key,
            'request_hash' => $hash,
            'response_status' => 201,
            'response_body' => json_encode(['data' => 'won'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->idempotency()->execute($user, 'op.test', $key, ['x' => 1], function (): array {
            $this->fail('Callback must not execute when a completed record already exists.');
        });

        $this->assertSame(201, $result['status']);
        $this->assertSame('won', $result['body']['data']);
        $this->assertDatabaseCount('idempotency_records', 1);
    }

    public function test_actor_isolation_separates_keys_by_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $service = $this->idempotency();
        $a = $service->execute($alice, 'op.test', 'shared-key', ['x' => 1], fn () => ['status' => 201, 'body' => ['who' => 'alice']]);
        $b = $service->execute($bob, 'op.test', 'shared-key', ['x' => 1], fn () => ['status' => 201, 'body' => ['who' => 'bob']]);

        $this->assertSame('alice', $a['body']['who']);
        $this->assertSame('bob', $b['body']['who']);
        $this->assertDatabaseCount('idempotency_records', 2);
    }

    private function idempotency(): Idempotency
    {
        return $this->app->make(Idempotency::class);
    }
}
