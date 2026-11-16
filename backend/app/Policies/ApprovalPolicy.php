<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Approval;
use App\Models\User;

class ApprovalPolicy
{
    public function viewPending(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole(Role::APPROVER->value);
    }

    public function decide(User $user, Approval $approval): bool
    {
        return $user->id === $approval->approver_id;
    }
}
