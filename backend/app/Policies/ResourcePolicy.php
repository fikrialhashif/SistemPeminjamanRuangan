<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Resource;
use App\Models\User;

class ResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Resource $resource): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole(Role::RESOURCE_MANAGER->value);
    }

    public function update(User $user, Resource $resource): bool
    {
        return $user->isAdmin() || $user->hasRole(Role::RESOURCE_MANAGER->value);
    }

    public function delete(User $user, Resource $resource): bool
    {
        return $user->isAdmin();
    }
}
