<?php

namespace App\Domain\Coordination\Enums;

enum ReferralLifecycleEventType: string
{
    case Proposed = 'proposed';
    case Offered = 'offered';
    case Viewed = 'viewed';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Reassigned = 'reassigned';
    case CoordinatorOverride = 'coordinator_override';
    case SilentLoss = 'silent_loss';

    public function requiresReason(): bool
    {
        return $this === self::Reassigned || $this === self::CoordinatorOverride;
    }
}
