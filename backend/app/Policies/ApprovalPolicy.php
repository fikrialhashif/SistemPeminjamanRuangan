<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Approval;
use App\Models\User;

class ApprovalPolicy
{
    public function decide(User $user, Approval $approval, bool $onWebPanel = false): bool
    {
        return Approval::query()
            ->whereKey($approval->id)
            ->withinAuthorityOf($user, Permission::APPROVALS_UPDATE, $onWebPanel)
            ->exists();
    }
}
