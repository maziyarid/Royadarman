<?php

namespace App\Domain\Operations\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Idempotency
{
    /** @return array{status:int,body:array<string,mixed>} */
    public function execute(?User $actor, string $operation, string $key, array $request, callable $callback): array
    {
        if ($key === '' || strlen($key) > 100) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid Idempotency-Key header is required.']);
        }
        $hash = hash('sha256', json_encode($request, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $operation, $key, $hash, $callback): array {
            $record = DB::table('idempotency_records')->where(['actor_user_id' => $actor?->id, 'operation' => $operation, 'idempotency_key' => $key])->lockForUpdate()->first();
            if ($record) {
                if (! hash_equals($record->request_hash, $hash)) {
                    abort(409, 'Idempotency key reused with a different request.');
                }
                if ($record->completed_at) {
                    return ['status' => (int) $record->response_status, 'body' => json_decode($record->response_body, true, 512, JSON_THROW_ON_ERROR)];
                }
                abort(409, 'Request is already in progress.');
            }
            $id = (string) Str::ulid();
            DB::table('idempotency_records')->insert(['id' => $id, 'actor_user_id' => $actor?->id, 'operation' => $operation, 'idempotency_key' => $key, 'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $result = $callback();
            DB::table('idempotency_records')->where('id', $id)->update(['response_status' => $result['status'], 'response_body' => json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'completed_at' => now(), 'updated_at' => now()]);

            return $result;
        });
    }
}

