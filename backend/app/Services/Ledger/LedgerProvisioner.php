<?php

namespace App\Services\Ledger;

use App\Models\FinancialAccount;
use App\Models\LedgerAccount;
use App\Models\Organization;

/**
 * Sets up a chama's chart of accounts: the fixed system accounts every chama needs (member
 * savings liability, penalty income, general expense) when the chama is created, and a backing
 * asset ledger account whenever a new FinancialAccount is added.
 */
class LedgerProvisioner
{
    public function provisionSystemAccounts(Organization $organization): void
    {
        $defaults = [
            [
                'code' => LedgerAccount::CODE_MEMBER_SAVINGS,
                'name' => 'Member Savings',
                'type' => LedgerAccount::TYPE_LIABILITY,
            ],
            [
                'code' => LedgerAccount::CODE_PENALTY_INCOME,
                'name' => 'Penalties & Other Income',
                'type' => LedgerAccount::TYPE_INCOME,
            ],
            [
                'code' => LedgerAccount::CODE_GENERAL_EXPENSE,
                'name' => 'General Expenses',
                'type' => LedgerAccount::TYPE_EXPENSE,
            ],
        ];

        foreach ($defaults as $account) {
            LedgerAccount::firstOrCreate(
                ['organization_id' => $organization->id, 'code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type'], 'is_system' => true],
            );
        }
    }

    public function provisionForFinancialAccount(FinancialAccount $financialAccount): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['organization_id' => $financialAccount->organization_id, 'code' => 'financial_account_'.$financialAccount->id],
            [
                'name' => $financialAccount->name,
                'type' => LedgerAccount::TYPE_ASSET,
                'is_system' => false,
                'financial_account_id' => $financialAccount->id,
            ],
        );
    }
}
