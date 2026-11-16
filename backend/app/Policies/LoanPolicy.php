<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\AssetLoan;
use App\Models\User;

class LoanPolicy
{
    public function view(User $user, AssetLoan $loan): bool
    {
        return $user->id === $loan->user_id
            || $user->isAdmin()
            || $user->hasRole(Role::RESOURCE_MANAGER->value);
    }

    public function handover(User $user, AssetLoan $loan): bool
    {
        return $user->hasRole(Role::RESOURCE_MANAGER->value);
    }

    public function returnAsset(User $user, AssetLoan $loan): bool
    {
        return $user->hasRole(Role::RESOURCE_MANAGER->value);
    }

    public function reportDamage(User $user, AssetLoan $loan): bool
    {
        return $user->id === $loan->user_id
            || $user->hasRole(Role::RESOURCE_MANAGER->value);
    }
}
