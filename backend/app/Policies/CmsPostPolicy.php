<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Cms\Post;
use App\Models\User;

final class CmsPostPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true)
            || $this->isCmsEditor($user);
    }

    public function view(User $user, Post $post): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Post $post): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $post->status->canBeEdited();
    }

    public function publish(User $user, Post $post): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true)
            || $this->isCmsEditor($user);
    }

    public function delete(User $user, Post $post): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true);
    }

    private function isCmsEditor(User $user): bool
    {
        return in_array($user->role, [UserRole::Coordinator], true);
    }
}
