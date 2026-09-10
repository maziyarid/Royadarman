<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Models\SupportConversation;
use App\Models\User;

final class SupportConversationPolicy
{
    public function view(User $user, SupportConversation $conversation): bool
    {
        if ($user->role === UserRole::Patient) {
            return (int) $conversation->patient_user_id === (int) $user->id;
        }

        if ($user->role === UserRole::Coordinator) {
            return $conversation->assignee_user_id !== null
                && (int) $conversation->assignee_user_id === (int) $user->id;
        }

        if ($user->role === UserRole::Owner || $user->role === UserRole::TechnicalAdministrator) {
            return true;
        }

        return false;
    }

    public function reply(User $user, SupportConversation $conversation): bool
    {
        if ($user->role === UserRole::Patient) {
            return (int) $conversation->patient_user_id === (int) $user->id
                && $conversation->status->isOpen();
        }

        if ($user->role === UserRole::Coordinator) {
            return $conversation->assignee_user_id !== null
                && (int) $conversation->assignee_user_id === (int) $user->id;
        }

        return false;
    }

    public function addInternalNote(User $user, SupportConversation $conversation): bool
    {
        if ($user->role === UserRole::Coordinator) {
            return $conversation->assignee_user_id !== null
                && (int) $conversation->assignee_user_id === (int) $user->id;
        }

        return $user->role === UserRole::Owner;
    }

    public function changeStatus(User $user, SupportConversation $conversation): bool
    {
        if ($user->role === UserRole::Coordinator) {
            return $conversation->assignee_user_id !== null
                && (int) $conversation->assignee_user_id === (int) $user->id;
        }

        return $user->role === UserRole::Owner;
    }
}
