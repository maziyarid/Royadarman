<?php

namespace App\Domain\Identity\Tenancy;

use JsonSerializable;

/** A selected scope snapshot, never a reusable authorization token. */
final readonly class WorkspaceContext implements JsonSerializable
{
    public function __construct(
        public int $actorId,
        public string $clinicId,
        public string $branchId,
        public string $membershipId,
        public string $workspaceRole,
        public int $membershipVersion,
    ) {}

    public function jsonSerialize(): array
    {
        return ['version' => 1, 'actor_user_id' => $this->actorId, 'clinic_id' => $this->clinicId,
            'branch_id' => $this->branchId, 'membership_id' => $this->membershipId,
            'workspace_role' => $this->workspaceRole, 'membership_version' => $this->membershipVersion];
    }
}
