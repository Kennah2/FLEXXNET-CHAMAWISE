<?php

namespace App\Services\Accounts;

use App\Models\AccountReconciliation;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Ledger\LedgerProvisioner;

/**
 * Every write action on a FinancialAccount, shared between the web controller and the v1 API
 * controller (Part 15) — creating an account (provisioning its backing ledger account and
 * default-account bookkeeping) and recording a reconciliation.
 */
class FinancialAccountService
{
    public function __construct(
        private readonly LedgerProvisioner $provisioner,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(
        Organization $organization,
        string $name,
        string $type,
        ?string $accountNumber,
        int $openingBalanceCents,
        bool $wantsDefault,
        User $actor,
    ): FinancialAccount {
        $this->provisioner->provisionSystemAccounts($organization);

        $isFirstAccount = ! $organization->financialAccounts()->exists();

        $account = FinancialAccount::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'type' => $type,
            'account_number' => $accountNumber,
            'currency' => $organization->currency,
            'opening_balance_cents' => $openingBalanceCents,
            'is_default' => $isFirstAccount || $wantsDefault,
            'created_by' => $actor->id,
        ]);

        if ($account->is_default) {
            $organization->financialAccounts()->where('id', '!=', $account->id)->update(['is_default' => false]);
        }

        $this->provisioner->provisionForFinancialAccount($account);

        $this->auditLogger->log(
            'financial_account.created',
            $account,
            organizationId: $organization->id,
            userId: $actor->id,
            after: $account->only(['name', 'type', 'opening_balance_cents']),
        );

        return $account;
    }

    public function reconcile(
        Organization $organization,
        FinancialAccount $account,
        string $statementDate,
        int $statementBalanceCents,
        ?string $notes,
        User $actor,
    ): AccountReconciliation {
        abort_unless($account->organization_id === $organization->id, 404);

        $computedBalanceCents = $account->balanceCents();

        $reconciliation = AccountReconciliation::create([
            'organization_id' => $organization->id,
            'financial_account_id' => $account->id,
            'statement_date' => $statementDate,
            'statement_balance_cents' => $statementBalanceCents,
            'computed_balance_cents' => $computedBalanceCents,
            'variance_cents' => $statementBalanceCents - $computedBalanceCents,
            'notes' => $notes,
            'recorded_by' => $actor->id,
        ]);

        $this->auditLogger->log(
            'account_reconciliation.recorded',
            $reconciliation,
            organizationId: $organization->id,
            userId: $actor->id,
            after: $reconciliation->only(['statement_balance_cents', 'computed_balance_cents', 'variance_cents']),
        );

        return $reconciliation;
    }
}
