<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class NotificationCallbackController extends Controller
{
    /**
     * Lifecycle precedence (lower index = earlier step). queued -> sent is the
     * only forward progression we always honour; delivered and failed are both
     * terminal. A late terminal callback never overwrites an already-terminal
     * delivery, so a delayed failure cannot corrupt a confirmed delivery and a
     * delayed delivery cannot resurrect a recorded failure.
     */
    private const STATUS_ORDER = ['queued' => 0, 'sending' => 0, 'sent' => 1, 'delivered' => 2, 'failed' => 2];

    private const MAX_SKEW_SECONDS = 300;

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('royadarman.sms.callback_secret');
        $timestamp = (string) $request->header('X-Callback-Timestamp');
        $signature = (string) $request->header('X-Callback-Signature');

        if ($secret === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            abort(401);
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            abort(401);
        }

        $data = $request->validate([
            'reference' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:queued,sending,sent,delivered,failed'],
            'failure_code' => ['nullable', 'string', 'max:80'],
        ]);

        $updated = DB::transaction(function () use ($data): int {
            $delivery = DB::table('notification_deliveries')
                ->where('channel', 'sms')
                ->where('provider_reference', $data['reference'])
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                return 0;
            }

            $incoming = self::STATUS_ORDER[$data['status']] ?? null;
            $current = self::STATUS_ORDER[$delivery->status] ?? null;
            if ($incoming === null || $current === null || $incoming <= $current) {
                return 2;
            }

            DB::table('notification_deliveries')
                ->where('id', $delivery->id)
                ->update([
                    'status' => $data['status'],
                    'failure_code' => $data['failure_code'] ?? null,
                    'updated_at' => now(),
                ]);

            return 1;
        });

        return response()->json(['data' => ['accepted' => true, 'applied' => $updated === 1]]);
    }
}
