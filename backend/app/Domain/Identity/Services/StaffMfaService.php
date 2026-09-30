<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use App\Support\DigitNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class StaffMfaService
{
    public function __construct(private readonly TotpVerifier $totp) {}

    public function isConfigured(User $user): bool
    {
        return filled($user->totp_secret)
            || (is_array($user->mfa_recovery_codes) && count($user->mfa_recovery_codes) > 0);
    }

    public function verifyAndConsume(User $user, ?string $totpCode, ?string $recoveryCode): bool
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

    public function verifySecret(string $secret, string $code): bool
    {
        return $this->totp->verify($secret, DigitNormalizer::latin($code));
    }

    public function generateSecret(): string
    {
        return $this->base32(random_bytes(20));
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }

        return $codes;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(static fn (string $code): string => Hash::make($code), $codes);
    }

    public function otpauthUri(User $user, string $secret): string
    {
        $label = rawurlencode('Royadarman:'.($user->name ?: 'user-'.$user->id));
        $issuer = rawurlencode('Royadarman');

        return 'otpauth://totp/'.$label.'?secret='.$secret.'&issuer='.$issuer.'&digits=6&period=30';
    }

    private function base32(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }

            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }
}
