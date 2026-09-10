<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PolicyVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PolicyController extends Controller
{
    public function show(Request $request, string $key): JsonResponse
    {
        $locale = app()->getLocale();
        $policy = PolicyVersion::query()->where('policy_key', $key)->where('locale', $locale)->whereNotNull('published_at')->latest('published_at')->first();
        if (! $policy) {
            return response()->json(['error' => ['code' => 'error.consent.translation_unavailable'], 'request_id' => $request->attributes->get('request_id')], 503);
        }

        return response()->json(['data' => ['key' => $key, 'version' => $policy->version, 'locale' => $locale, 'content' => $policy->content, 'content_hash' => $policy->content_hash]])->header('Cache-Control', 'private, no-store');
    }
}
