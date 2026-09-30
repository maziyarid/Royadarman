<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\OtpService;
use App\Domain\Identity\Services\StaffMfaService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DigitNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class AuthController extends Controller
{
    public function challenge(Request $request, OtpService $service, StaffMfaService $mfa): JsonResponse
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'max:32'], 'locale' => ['required', 'in:fa,ar,en']]);
        $challenge = $service->challenge($data['mobile'], $data['locale'], (string) $request->ip());
        $user = User::query()->where('phone_hash', $challenge->phone_hash)->first();

        return response()->json([
            'data' => [
                'challenge_id' => $challenge->id,
                'expires_in' => (int) config('royadarman.sms.otp.ttl_seconds', 300),
                'resend_in' => (int) config('royadarman.sms.otp.resend_cooldown_seconds', 60),
                'code_length' => (int) config('royadarman.sms.otp.length', 6),
                'requires_mfa' => $user?->role->isStaff() === true && $mfa->isConfigured($user),
            ],
        ], 202);
    }

    public function verify(Request $request, OtpService $service): JsonResponse
    {
        $otpLength = (int) config('royadarman.sms.otp.length', 6);
        $data = $request->validate(['challenge_id' => ['required', 'string'], 'code' => ['required', 'string', 'size:'.$otpLength], 'totp_code' => ['nullable', 'string'], 'recovery_code' => ['nullable', 'string', 'max:100']]);
        $user = $service->verify($data['challenge_id'], DigitNormalizer::latin($data['code']), $data['totp_code'] ?? null, $data['recovery_code'] ?? null);
        Auth::login($user);
        $request->session()->put('auth_method', 'otp');
        $request->session()->regenerate();

        return response()->json(['data' => ['role' => $user->role->value, 'locale' => $user->locale]]);
    }

    public function password(Request $request, StaffMfaService $mfa): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:128'],
            'totp_code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:100'],
            'locale' => ['required', 'in:fa,ar,en'],
        ]);
        $username = mb_strtolower(trim($data['username']));
        $user = User::query()->where('username', $username)->first();
        $valid = $user !== null
            && $user->is_active
            && filled($user->phone_hash)
            && ! (is_string($user->email) && str_ends_with($user->email, '@royadarman.invalid'))
            && is_string($user->password)
            && Hash::check($data['password'], $user->password);

        if (! $valid) {
            return response()->json(['error' => ['message' => __('auth_ui.invalid_credentials')]], 422);
        }

        if ($user->role->isStaff() && $mfa->isConfigured($user)
            && ! $mfa->verifyAndConsume($user, $data['totp_code'] ?? null, $data['recovery_code'] ?? null)) {
            return response()->json(['error' => ['message' => __('auth_ui.invalid_credentials')]], 422);
        }

        $user->forceFill(['last_authenticated_at' => now(), 'locale' => $data['locale']])->save();
        Auth::login($user);
        $request->session()->put('auth_method', 'password');
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
