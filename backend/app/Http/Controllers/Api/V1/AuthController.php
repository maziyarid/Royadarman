<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use App\Support\DigitNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthController extends Controller
{
    public function challenge(Request $request, OtpService $service): JsonResponse
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'max:32'], 'locale' => ['required', 'in:fa,ar,en']]);
        $challenge = $service->challenge($data['mobile'], $data['locale'], (string) $request->ip());

        return response()->json(['data' => ['challenge_id' => $challenge->id, 'expires_in' => 300, 'resend_in' => 60]], 202);
    }

    public function verify(Request $request, OtpService $service): JsonResponse
    {
        $data = $request->validate(['challenge_id' => ['required', 'string'], 'code' => ['required', 'string', 'size:6'], 'totp_code' => ['nullable', 'string'], 'recovery_code' => ['nullable', 'string', 'max:100']]);
        $user = $service->verify($data['challenge_id'], DigitNormalizer::latin($data['code']), $data['totp_code'] ?? null, $data['recovery_code'] ?? null);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['data' => ['role' => $user->role->value, 'locale' => $user->locale]]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['logged_out' => true]]);
    }
}
