<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class LoanPolicy
{
    use ChecksChamaPermissions;

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->isActiveMember($user, $organization->id);
    }

    /**
     * Any active member may apply for a loan — for themselves always, or for another member
     * only if they hold manage-finances (an official helping a member apply), mirroring
     * ContributionPolicy::create.
     */
    public function create(User $user, Organization $organization, int $forUserId): bool
    {
        if (! $this->isActiveMember($user, $organization->id)) {
            return false;
        }

        if ($forUserId === $user->id) {
            return true;
        }

        return $this->hasPermission($user, $organization->id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function view(User $user, Loan $loan): bool
    {
        if ($user->id === $loan->user_id) {
            return true;
        }

        return $this->hasPermission($user, $loan->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    /**
     * Gates both approving and rejecting a pending application — the same "deciding on this
     * request" action the web/API controllers both check under this one ability, mirroring
     * WithdrawalRequestPolicy. A borrower can never approve (or reject) their own loan,
     * regardless of permission — never allowed, same as a withdrawal's self-approval block.
     */
    public function approve(User $user, Loan $loan): bool
    {
        if ($user->id === $loan->user_id) {
            return false;
        }

        return $this->hasPermission($user, $loan->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    /**
     * A borrower can never disburse their own loan to themselves, regardless of permission.
     */
    public function disburse(User $user, Loan $loan): bool
    {
        if ($user->id === $loan->user_id) {
            return false;
        }

        return $this->hasPermission($user, $loan->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function cancel(User $user, Loan $loan): bool
    {
        return $user->id === $loan->requested_by && $loan->status === Loan::STATUS_PENDING;
    }

    /**
     * Recording a repayment: the borrower paying back their own loan, or an official recording
     * on their behalf — mirrors ContributionPolicy::create's "self always, others need
     * manage-finances" shape.
     */
    public function recordRepayment(User $user, Loan $loan): bool
    {
        if ($user->id === $loan->user_id) {
            return true;
        }

        return $this->hasPermission($user, $loan->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }

    public function reverseRepayment(User $user, Loan $loan): bool
    {
        return $this->hasPermission($user, $loan->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }
}
