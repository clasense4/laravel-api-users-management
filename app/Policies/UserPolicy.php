<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the actor can edit the target user.
     *
     * Rules:
     *  - Administrator: can edit any user.
     *  - Manager: can edit only users whose role is `user`.
     *  - User: can edit only themselves.
     */
    public function update(User $actor, User $target): bool
    {
        return match ($actor->role) {
            UserRole::Administrator => true,
            UserRole::Manager => $target->role === UserRole::User,
            UserRole::User => $actor->is($target),
            default => false,
        };
    }
}
