<?php

namespace App\Domain\Cases\Enums;

enum CaseStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case AwaitingContact = 'awaiting_contact';
    case InCoordination = 'in_coordination';
    case AwaitingPatient = 'awaiting_patient';
    case ClinicianReview = 'clinician_review';
    case ReferralProposed = 'referral_proposed';
    case HomeVisitProposed = 'home_visit_proposed';
    case Referred = 'referred';
    case VisitScheduled = 'visit_scheduled';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';
    case Closed = 'closed';

    /** @return list<self> */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::AwaitingContact, self::Cancelled],
            self::AwaitingContact => [self::InCoordination, self::AwaitingPatient, self::Cancelled],
            self::InCoordination => [self::AwaitingPatient, self::ClinicianReview, self::ReferralProposed, self::HomeVisitProposed, self::Resolved, self::Cancelled],
            self::AwaitingPatient => [self::InCoordination, self::Cancelled],
            self::ClinicianReview => [self::InCoordination, self::Resolved, self::Cancelled],
            self::ReferralProposed => [self::Referred, self::InCoordination, self::Cancelled],
            self::HomeVisitProposed => [self::VisitScheduled, self::InCoordination, self::Cancelled],
            self::Referred, self::VisitScheduled => [self::Resolved, self::Cancelled],
            self::Resolved, self::Cancelled => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
