<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Operations\Contracts\SmsProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class NotificationCallbackController extends Controller
{
    public function __construct(
        private readonly SmsProvider $provider,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $headers = array_change_key_case($request->headers->all(), CASE_LOWER);
        $headers = array_map(fn ($value) => is_array($value) ? ($value[0] ?? '') : $value, $headers);

        if (! $this->provider->verifyCallback($rawBody, $headers)) {
            abort(401);
        }

        $parsed = $this->provider->parseCallback($rawBody, $headers);

        if (! in_array($parsed['status'], ['queued', 'sent', 'delivered', 'failed'], true)) {
            abort(422);
        }

        DB::table('notification_deliveries')
            ->where('provider_reference', $parsed['reference'])
            ->update([
                'status' => $parsed['status'],
                'failure_code' => $parsed['failure_code'],
                'updated_at' => now(),
            ]);

        return response()->json(['data' => ['accepted' => true]]);
    }
}
