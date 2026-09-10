<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Enums\UserRole;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\DigitNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class OtpService
{
    public function __construct(private readonly OtpSender $sender, private readonly TotpVerifier $totp) {}

    public function challenge(string $mobile, string $locale, string $ip): OtpChallenge
    {
        $mobile = DigitNormalizer::iranianMobile($mobile);
        if (! preg_match('/^09\d{9}$/', $mobile)) {
            throw ValidationException::withMessages(['mobile' => __('ui.errors.mobile')]);
        }

        $phoneHash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
        $ipHash = hash_hmac('sha256', $ip, (string) config('app.key'));
        if (OtpChallenge::query()->where('phone_hash', $phoneHash)->where('created_at', '>=', now()->subHour())->count() >= 10) {
            abort(429);
        }
        if (OtpChallenge::query()->where('request_ip_hash', $ipHash)->where('created_at', '>=', now()->subHour())->count() >= 20) {
            abort(429);
        }

        $latest = OtpChallenge::query()->where('phone_hash', $phoneHash)->latest()->first();
        if ($latest?->last_sent_at?->isAfter(now()->subMinute())) {
            abort(429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = OtpChallenge::query()->create([
            'phone' => $mobile, 'phone_hash' => $phoneHash, 'code_hash' => Hash::make($code),
            'locale' => $locale, 'purpose' => 'login', 'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(), 'request_ip_hash' => $ipHash,
        ]);

        try {
            $this->sender->send($mobile, $code, $locale);
        } catch (\Throwable $exception) {
            $challenge->delete();
            report($exception);
            abort(503, __('ui.errors.otp_delivery'));
        }

        return $challenge;
    }

    public function verify(string $challengeId, string $code, ?string $totpCode = null, ?string $recoveryCode = null): User
    {
        return DB::transaction(function () use ($challengeId, $code, $totpCode, $recoveryCode): User {
            $challenge = OtpChallenge::query()->lockForUpdate()->findOrFail($challengeId);
            if ($challenge->used_at || $challenge->expires_at->isPast() || $challenge->attempts >= 5) {
                throw ValidationException::withMessages(['code' => __('ui.errors.otp_invalid')]);
            }
            $challenge->increment('attempts');
            if (! Hash::check(DigitNormalizer::latin($code), $challenge->code_hash)) {
                throw ValidationException::withMessages(['code' => __('ui.errors.otp_invalid')]);
            }

            $user = User::query()->firstOrCreate(
                ['phone_hash' => $challenge->phone_hash],
                ['phone' => $challenge->phone, 'role' => UserRole::Patient, 'locale' => $challenge->locale, 'is_active' => true]
            );
            if (! $user->is_active) {
                abort(403);
            }
            if ($user->role->isStaff() && ! $this->verifyStaffMfa($user, $totpCode, $recoveryCode)) {
                throw ValidationException::withMessages(['totp_code' => __('ui.errors.mfa_invalid')]);
            }
            $challenge->update(['used_at' => now()]);
            $user->update(['last_authenticated_at' => now(), 'locale' => $challenge->locale]);

            return $user;
        });
    }

    private function verifyStaffMfa(User $user, ?string $totpCode, ?string $recoveryCode): bool
    {
        if ($user->totp_secret && $totpCode && $this->totp->verify($user->totp_secret, DigitNormalizer::latin($totpCode))) {
            return true;
        }
        if (! $recoveryCode || ! is_array($user->mfa_recovery_codes)) {
            return false;
        }
        foreach ($user->mfa_recovery_codes as $index => $hash) {
            if (is_string($hash) && Hash::check($recoveryCode, $hash)) {
                $codes = $user->mfa_recovery_codes;
                unset($codes[$index]);
                $user->update(['mfa_recovery_codes' => array_values($codes)]);

                return true;
            }
        }

        return false;
    }
}
