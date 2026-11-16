<?php

namespace App\Policies;

use App\Models\Strike;
use App\Models\User;

class StrikePolicy
{
    public function cancel(User $user, Strike $strike): bool
    {
        return $user->isAdmin();
    }
}
