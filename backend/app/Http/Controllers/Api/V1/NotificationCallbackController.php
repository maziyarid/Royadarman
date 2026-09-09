<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class NotificationCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $timestamp = (string) $request->header('X-Callback-Timestamp');
        $signature = (string) $request->header('X-Callback-Signature');
        $secret = (string) config('royadarman.sms.callback_secret');
        if ($secret === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            abort(401);
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            abort(401);
        }

        $data = $request->validate(['reference' => ['required', 'string', 'max:120'], 'status' => ['required', 'in:queued,sent,delivered,failed'], 'failure_code' => ['nullable', 'string', 'max:80']]);
        DB::table('notification_deliveries')->where('provider_reference', $data['reference'])->update(['status' => $data['status'], 'failure_code' => $data['failure_code'] ?? null, 'updated_at' => now()]);
        return response()->json(['data' => ['accepted' => true]]);
    }
}
