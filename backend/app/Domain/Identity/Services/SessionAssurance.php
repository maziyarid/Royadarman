<?php

namespace App\Domain\Identity\Services;

use Illuminate\Contracts\Session\Session;
use InvalidArgumentException;

/**
 * Per-session recent-authentication proof.
 *
 * The proof lives only in the session store, so a login on another device can
 * never refresh this session. users.last_authenticated_at remains an audit
 * value and must not be used as session proof.
 */
final class SessionAssurance
{
    public const KEY = 'recent_auth_at';

    public const METHOD_KEY = 'recent_auth_method';

    public const MAX_AGE_MINUTES = 30;

    private const METHODS = ['password', 'otp', 'passkey'];

    public function mark(Session $session, string $method): void
    {
        if (! in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException('Unsupported authentication method for session assurance.');
        }

        $session->put(self::KEY, now()->getTimestamp());
        $session->put(self::METHOD_KEY, $method);
    }

    public function clear(Session $session): void
    {
        $session->forget([self::KEY, self::METHOD_KEY]);
    }

    public function isFresh(Session $session): bool
    {
        $at = $session->get(self::KEY);

        if (! is_int($at)) {
            return false;
        }

        $now = now()->getTimestamp();

        return $at <= $now && $at >= $now - (self::MAX_AGE_MINUTES * 60);
    }
}
