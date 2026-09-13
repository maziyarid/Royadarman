<?php

namespace App\Domain\Identity\Enums;

enum CredentialStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function canPublish(): bool
    {
        return $this === self::Verified;
    }

    public function isActive(): bool
    {
        return $this === self::Verified;
    }
}
