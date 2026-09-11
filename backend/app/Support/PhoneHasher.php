<?php

namespace App\Support;

use RuntimeException;

final class PhoneHasher
{
    private readonly string $key;

    public function __construct()
    {
        $key = (string) config('royadarman.phone_hash_key');

        if ($key === '') {
            throw new RuntimeException(
                'No phone lookup key is configured. Set ROYADARMAN_PHONE_HASH_KEY to a non-empty high-entropy secret before hashing phone identities.'
            );
        }

        $appKey = (string) config('app.key');
        if ($appKey !== '' && hash_equals($appKey, $key)) {
            throw new RuntimeException(
                'ROYADARMAN_PHONE_HASH_KEY must not equal APP_KEY. It is an independent lookup secret; reusing APP_KEY defeats the separate lookup-secret architecture.'
            );
        }

        $this->key = $key;
    }

    public function hash(string $normalisedMobile): string
    {
        return hash_hmac('sha256', $normalisedMobile, $this->key);
    }
}
