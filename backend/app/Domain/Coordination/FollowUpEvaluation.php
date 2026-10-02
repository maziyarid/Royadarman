<?php

namespace App\Domain\Coordination;

/**
 * Proposed follow-up evaluation result (P05). Not wired into any controller,
 * policy, route or job. Reason codes describe contract state, not
 * authorizations.
 */
final readonly class FollowUpEvaluation
{
    public function __construct(
        public bool $due,
        public bool $overdue,
        public bool $escalate,
        public string $level,
        public string $reason,
    ) {
    }
}
