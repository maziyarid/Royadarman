<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Enums\UserRole;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\DigitNormalizer;
use App\Support\DomainException;
use App\Support\PhoneHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OtpService
{
    public function __construct(private readonly OtpSender $sender, private readonly StaffMfaService $mfa, private readonly PhoneHasher $phoneHasher) {}

    public function challenge(string $mobile, string $locale, string $ip): OtpChallenge
    {
        $mobile = DigitNormalizer::iranianMobile($mobile);
        if (! preg_match('/^09\d{9}$/', $mobile)) {
            throw ValidationException::withMessages(['mobile' => __('ui.errors.mobile')]);
        }

        $phoneHash = $this->phoneHasher->hash($mobile);
        if (! config('royadarman.intake_enabled') && ! User::query()->where('phone_hash', $phoneHash)->exists()) {
            throw new DomainException(503, 'intake_not_enabled', trans('auth_ui.existing_only', [], $locale));
        }

        $ipHash = hash_hmac('sha256', $ip, (string) config('app.key'));
        $phoneLimit = (int) config('royadarman.sms.otp.phone_limit_count', 3);
        $phoneWindow = (int) config('royadarman.sms.otp.phone_limit_window_minutes', 10);
        $ipLimit = (int) config('royadarman.sms.otp.ip_limit_count', 10);
        $ipWindow = (int) config('royadarman.sms.otp.ip_limit_window_minutes', 60);
        $resendCooldown = (int) config('royadarman.sms.otp.resend_cooldown_seconds', 60);
        $length = (int) config('royadarman.sms.otp.length', 6);
        $ttlSeconds = (int) config('royadarman.sms.otp.ttl_seconds', 300);

        if (OtpChallenge::query()->where('phone_hash', $phoneHash)->where('created_at', '>=', now()->subMinutes($phoneWindow))->count() >= $phoneLimit) {
            abort(429);
        }
        if (OtpChallenge::query()->where('request_ip_hash', $ipHash)->where('created_at', '>=', now()->subMinutes($ipWindow))->count() >= $ipLimit) {
            abort(429);
        }

        $latest = OtpChallenge::query()->where('phone_hash', $phoneHash)->latest()->first();
        if ($latest?->last_sent_at?->isAfter(now()->subSeconds($resendCooldown))) {
            abort(429);
        }

        $max = (10 ** $length) - 1;
        $code = str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
        $challenge = DB::transaction(function () use ($mobile, $locale, $phoneHash, $ipHash, $code, $ttlSeconds): OtpChallenge {
            // Acquire a durable serialization slot keyed by phone_hash + purpose.
            // The slot row always exists (created on first use), so locking it
            // serialises concurrent issuance transactions even when no existing
            // active challenge row exists to lockForUpdate. This makes the
            // one-active-challenge-per-identity invariant structurally race-safe.
            DB::table('otp_issuance_locks')
                ->insertOrIgnore(['id' => (string) Str::ulid(), 'phone_hash' => $phoneHash, 'purpose' => 'login', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('otp_issuance_locks')
                ->where('phone_hash', $phoneHash)
                ->where('purpose', 'login')
                ->lockForUpdate()
                ->first();

            // Atomically supersede all prior active challenges for this phone+
            // purpose before the new challenge becomes usable, so only one active
            // challenge can exist per identity at a time, serialised against
            // concurrent issuance by the durable lock slot above.
            OtpChallenge::query()
                ->where('phone_hash', $phoneHash)
                ->where('purpose', 'login')
                ->whereNull('used_at')
                ->whereNull('superseded_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->update(['superseded_at' => now()]);

            return OtpChallenge::query()->create([
                'phone' => $mobile, 'phone_hash' => $phoneHash, 'code_hash' => Hash::make($code),
                'locale' => $locale, 'purpose' => 'login', 'expires_at' => now()->addSeconds($ttlSeconds),
                'last_sent_at' => now(), 'request_ip_hash' => $ipHash,
            ]);
        });

        try {
            $this->sender->send($mobile, $code, $locale);
        } catch (\Throwable $exception) {
            $challenge->delete();
            report($exception);

            throw new DomainException(
                503,
                'auth.otp_delivery_unavailable',
                trans('auth_ui.otp_unavailable', [], $locale),
                $exception,
            );
        }

        return $challenge;
    }

    public function verify(string $challengeId, string $code, ?string $totpCode = null, ?string $recoveryCode = null): User
    {
        return DB::transaction(function () use ($challengeId, $code, $totpCode, $recoveryCode): User {
            $challenge = OtpChallenge::query()->lockForUpdate()->findOrFail($challengeId);
            $maxAttempts = (int) config('royadarman.sms.otp.max_attempts', 5);
            if ($challenge->used_at || $challenge->superseded_at || $challenge->expires_at->isPast() || $challenge->attempts >= $maxAttempts) {
                throw ValidationException::withMessages(['code' => __('ui.errors.otp_invalid')]);
            }
            $challenge->increment('attempts');
            if (! Hash::check(DigitNormalizer::latin($code), $challenge->code_hash)) {
                throw ValidationException::withMessages(['code' => __('ui.errors.otp_invalid')]);
            }

            $user = User::query()->where('phone_hash', $challenge->phone_hash)->first();
            if (! $user && ! config('royadarman.intake_enabled')) {
                throw new DomainException(503, 'intake_not_enabled', trans('auth_ui.existing_only', [], $challenge->locale));
            }
            $user ??= User::query()->create([
                'phone' => $challenge->phone,
                'phone_hash' => $challenge->phone_hash,
                'role' => UserRole::Patient,
                'locale' => $challenge->locale,
                'is_active' => true,
            ]);
            if (! $user->is_active) {
                abort(403);
            }
            if ($user->role->isStaff() && $this->mfa->isConfigured($user)
                && ! $this->mfa->verifyAndConsume($user, $totpCode, $recoveryCode)) {
                throw ValidationException::withMessages(['totp_code' => __('ui.errors.mfa_invalid')]);
            }
            $challenge->update(['used_at' => now()]);
            $user->update(['last_authenticated_at' => now(), 'locale' => $challenge->locale]);

            return $user;
        });
    }
}
