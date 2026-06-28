<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function view(User $actor, User $target): bool
    {
        return true;
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->id === $target->id;
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->id === $target->id;
    }

    public function follow(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $target->isActive();
    }

    public function manageUsers(User $actor): bool
    {
        return $actor->hasRole(UserRole::Admin, UserRole::Moderator);
    }
}
