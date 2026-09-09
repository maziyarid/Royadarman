<?php

namespace App\Domain\Cases\Enums;

enum HomeServiceStatus: string
{
    case Requested = 'requested';
    case AreaVerified = 'area_verified';
    case CoordinatorReview = 'coordinator_review';
    case ProviderRequested = 'provider_requested';
    case ProviderAccepted = 'provider_accepted';
    case PatientConfirmed = 'patient_confirmed';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case UnableToService = 'unable_to_service';

    /** @return list<self> */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Requested => [self::AreaVerified, self::Rejected, self::UnableToService, self::Cancelled],
            self::AreaVerified => [self::CoordinatorReview, self::UnableToService, self::Cancelled],
            self::CoordinatorReview => [self::ProviderRequested, self::UnableToService, self::Cancelled],
            self::ProviderRequested => [self::ProviderAccepted, self::Rejected, self::Cancelled],
            self::ProviderAccepted => [self::PatientConfirmed, self::Rejected, self::Cancelled],
            self::PatientConfirmed => [self::Scheduled, self::Cancelled],
            self::Scheduled => [self::Completed, self::Cancelled],
            self::Completed, self::Rejected, self::UnableToService, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed
            || $this === self::Rejected
            || $this === self::UnableToService
            || $this === self::Cancelled;
    }
}
