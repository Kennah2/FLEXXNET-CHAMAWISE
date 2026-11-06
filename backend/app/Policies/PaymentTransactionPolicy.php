<?php

namespace App\Policies;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Policies\Concerns\ChecksChamaPermissions;
use App\Support\ChamaPermissions;

class PaymentTransactionPolicy
{
    use ChecksChamaPermissions;

    public function view(User $user, PaymentTransaction $transaction): bool
    {
        if ($user->id === $transaction->user_id) {
            return true;
        }

        return $this->hasPermission($user, $transaction->organization_id, ChamaPermissions::MANAGE_FINANCES);
    }
}
