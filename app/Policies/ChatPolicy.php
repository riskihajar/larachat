<?php

namespace App\Policies;

use App\Models\Chat;
use App\Models\User;

class ChatPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('chat.view.all');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Chat $chat): bool
    {
        // Admin can view all chats
        if ($user->can('chat.view.all')) {
            return true;
        }

        // Users can view their own chats
        return $user->can('chat.view.own') && $user->id === $chat->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('chat.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Chat $chat): bool
    {
        // Admin can update all chats
        if ($user->can('chat.update.all')) {
            return true;
        }

        // Users can update their own chats
        return $user->can('chat.update.own') && $user->id === $chat->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Chat $chat): bool
    {
        // Admin can delete all chats
        if ($user->can('chat.delete.all')) {
            return true;
        }

        // Users can delete their own chats
        return $user->can('chat.delete.own') && $user->id === $chat->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Chat $chat): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Chat $chat): bool
    {
        return false;
    }
}
