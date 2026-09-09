<?php

namespace App\Domain\Identity\Services;

final class TotpVerifier
{
    public function verify(string $base32Secret, string $code, ?int $timestamp = null): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $secret = $this->decodeBase32($base32Secret);
        $counter = intdiv($timestamp ?? time(), 30);

        for ($window = -1; $window <= 1; $window++) {
            $binary = pack('N*', 0).pack('N*', $counter + $window);
            $hash = hash_hmac('sha1', $binary, $secret, true);
            $offset = ord($hash[19]) & 0x0F;
            $value = ((ord($hash[$offset]) & 0x7F) << 24)
                | ((ord($hash[$offset + 1]) & 0xFF) << 16)
                | ((ord($hash[$offset + 2]) & 0xFF) << 8)
                | (ord($hash[$offset + 3]) & 0xFF);
            if (hash_equals(str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT), $code)) {
                return true;
            }
        }

        return false;
    }

    private function decodeBase32(string $value): string
    {
        $alphabet = '[REDACTED:entropy:32]';
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/\s+/', '', $value) ?? '')) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}

