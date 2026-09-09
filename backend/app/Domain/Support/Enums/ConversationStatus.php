<?php

namespace App\Domain\Support\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case AwaitingPatient = 'awaiting_patient';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';

    /** @return list<self> */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::AwaitingPatient, self::Resolved, self::Closed],
            self::InProgress => [self::AwaitingPatient, self::Resolved, self::Closed],
            self::AwaitingPatient => [self::InProgress, self::Resolved, self::Closed],
            self::Resolved => [self::Closed, self::Reopened],
            self::Reopened => [self::InProgress, self::Resolved, self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved && $this !== self::Closed;
    }
}
