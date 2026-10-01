<?php

namespace App\Domain\Identity\Authorization;

final class MembershipPermission
{
    public function __construct(
        public bool $allowed,
        public string $reason,
    ) {
    }
}
