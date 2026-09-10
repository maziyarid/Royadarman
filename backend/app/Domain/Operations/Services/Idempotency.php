<?php

namespace App\Domain\Operations\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $lookup = ['actor_user_id' => $actor?->id, 'operation' => $operation, 'idempotency_key' => $key];

        return DB::transaction(function () use ($lookup, $hash, $callback): array {
            $record = DB::table('idempotency_records')->where($lookup)->lockForUpdate()->first();
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
            try {
                DB::table('idempotency_records')->insert(array_merge($lookup, ['id' => $id, 'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]));
            } catch (UniqueConstraintViolationException) {
                $existing = DB::table('idempotency_records')->where($lookup)->lockForUpdate()->first();
                if ($existing && hash_equals($existing->request_hash, $hash) && $existing->completed_at) {
                    return ['status' => (int) $existing->response_status, 'body' => json_decode($existing->response_body, true, 512, JSON_THROW_ON_ERROR)];
                }
                abort(409, 'Request is already in progress.');
            }
            $result = $callback();
            DB::table('idempotency_records')->where('id', $id)->update(['response_status' => $result['status'], 'response_body' => json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'completed_at' => now(), 'updated_at' => now()]);

            return $result;
        });
    }
}
