<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => ['id' => $user->id, 'role' => $user->role->value, 'locale' => $user->locale, 'name' => $user->name]]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['locale' => ['sometimes', 'in:fa,ar,en'], 'name' => ['sometimes', 'nullable', 'string', 'max:80']]);
        $request->user()->update($data);

        return $this->show($request);
    }
}
