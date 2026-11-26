<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AssetLoan;
use App\Models\User;

class LoanPolicy
{
    public function view(User $user, AssetLoan $loan): bool
    {
        return $user->id === $loan->user_id
            || $user->hasPermission(Permission::LOANS_VIEW);
    }

    public function handover(User $user, AssetLoan $loan): bool
    {
        return $user->hasPermission(Permission::LOANS_UPDATE);
    }

    public function returnAsset(User $user, AssetLoan $loan): bool
    {
        return $user->hasPermission(Permission::LOANS_UPDATE);
    }

    public function reportDamage(User $user, AssetLoan $loan): bool
    {
        return $user->id === $loan->user_id
            || $user->hasPermission(Permission::LOANS_UPDATE);
    }
}
