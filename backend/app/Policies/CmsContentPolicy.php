<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\User;

final class CmsContentPolicy
{
    public function manage(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::TechnicalAdministrator, UserRole::Coordinator], true);
    }

    public function delete(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true);
    }
}
