<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\ProfilePreferences;
use App\Domain\Identity\Services\SelfProfileProjection;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => app(SelfProfileProjection::class)->forUser($request->user())])
            ->header('Cache-Control', 'private, no-store')
            ->header('Pragma', 'no-cache');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['locale' => ['sometimes', 'in:fa,ar,en'], 'name' => ['sometimes', 'nullable', 'string', 'max:80']]);
        app(ProfilePreferences::class)->update($request->user(), $data, $request->attributes->get('request_id'));

        return $this->show($request);
    }
}
