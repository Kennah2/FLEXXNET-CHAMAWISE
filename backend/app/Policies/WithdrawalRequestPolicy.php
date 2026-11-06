<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class WithdrawalRequestPolicy
{
    use ChecksChamaPermissions;

    /**
     * Any active member may request a withdrawal — from their own savings only (Phase 3 scope;
     * an official requesting on a member's behalf is not yet supported).
     */
    public function create(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization->id);
    }

    public function view(User $user, WithdrawalRequest $withdrawal): bool
    {
        if ($user->id === $withdrawal->user_id) {
            return true;
        }

        return $this->hasPermission($user, $withdrawal->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization->id);
    }

    public function cancel(User $user, WithdrawalRequest $withdrawal): bool
    {
        return $user->id === $withdrawal->requested_by
            && in_array($withdrawal->status, [WithdrawalRequest::STATUS_PENDING, WithdrawalRequest::STATUS_APPROVED], true);
    }

    /**
     * A chama's own officials cannot rubber-stamp their own withdrawal — never allowed,
     * regardless of permission (Part 14).
     */
    public function approve(User $user, WithdrawalRequest $withdrawal): bool
    {
        if ($user->id === $withdrawal->user_id) {
            return false;
        }

        return $this->hasPermission($user, $withdrawal->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function pay(User $user, WithdrawalRequest $withdrawal): bool
    {
        return $this->hasPermission($user, $withdrawal->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function reverse(User $user, WithdrawalRequest $withdrawal): bool
    {
        return $this->hasPermission($user, $withdrawal->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }
}
