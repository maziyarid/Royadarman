<?php

namespace App\Listeners;

use App\Domain\Identity\Services\SessionAssurance;
use App\Models\User;
use Laravel\Passkeys\Events\PasskeyVerified;

/**
 * Stamps the current browser session after a verified passkey login.
 *
 * Registered by Laravel listener auto-discovery (handle() type-hint), so the
 * shared AppServiceProvider is not edited. Discovery and event/login ordering
 * against the passkeys package are UNVERIFIED (no runtime); see the PR notes.
 */
final class MarkPasskeySessionAssurance
{
    public function __construct(private readonly SessionAssurance $assurance) {}

    public function handle(PasskeyVerified $event): void
    {
        $request = request();

        if (! $event->user instanceof User || ! $event->user->is_active || ! $request->hasSession()) {
            return;
        }

        $this->assurance->mark($request->session(), 'passkey');
    }
}
