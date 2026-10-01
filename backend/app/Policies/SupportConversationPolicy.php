<?php

namespace App\Policies;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Services\StaffCapabilities;
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
            if ($conversation->assignee_user_id === null) {
                return true;
            }

            return (int) $conversation->assignee_user_id === (int) $user->id;
        }

        if (StaffCapabilities::can($user->role, 'support.view')) {
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

        return StaffCapabilities::can($user->role, 'support.reply')
            && $this->view($user, $conversation);
    }

    public function addInternalNote(User $user, SupportConversation $conversation): bool
    {
        if ($user->role === UserRole::Coordinator) {
            return $conversation->assignee_user_id !== null
                && (int) $conversation->assignee_user_id === (int) $user->id;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        return StaffCapabilities::can($user->role, 'support.internal_note')
            && $this->view($user, $conversation);
    }

    public function changePriority(User $user, SupportConversation $conversation): bool
    {
        if ($user->role === UserRole::Coordinator) {
            return $conversation->assignee_user_id !== null
                && (int) $conversation->assignee_user_id === (int) $user->id;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        return StaffCapabilities::can($user->role, 'support.change_status')
            && $this->view($user, $conversation);
    }

    public function changeStatus(User $user, SupportConversation $conversation): bool
    {
        if ($user->role === UserRole::Coordinator) {
            return $conversation->assignee_user_id !== null
                && (int) $conversation->assignee_user_id === (int) $user->id;
        }

        if ($user->role === UserRole::Owner) {
            return true;
        }

        return StaffCapabilities::can($user->role, 'support.change_status')
            && $this->view($user, $conversation);
    }

    public function assign(User $user, SupportConversation $conversation): bool
    {
        if ($user->role === UserRole::Owner) {
            return true;
        }

        if ($user->role === UserRole::Coordinator) {
            return $conversation->assignee_user_id === null
                || (int) $conversation->assignee_user_id === (int) $user->id;
        }

        return StaffCapabilities::can($user->role, 'support.assign')
            && $this->view($user, $conversation);
    }
}
