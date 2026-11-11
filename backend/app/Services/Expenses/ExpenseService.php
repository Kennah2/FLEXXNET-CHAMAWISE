<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;

/**
 * Every write action on an Expense, shared between the web controller and the v1 API controller
 * (Part 15) — recording (which immediately posts the ledger, unlike withdrawals this needs no
 * approval workflow per Part 17) and reversing.
 */
class ExpenseService
{
    public function __construct(
        private readonly LedgerProvisioner $provisioner,
        private readonly LedgerPoster $poster,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function record(Organization $organization, FinancialAccount $account, int $amountCents, string $description, string $expenseDate, User $actor): Expense
    {
        $this->provisioner->provisionSystemAccounts($organization);

        $expenseLedgerAccount = LedgerAccount::where('organization_id', $organization->id)
            ->where('code', LedgerAccount::CODE_GENERAL_EXPENSE)
            ->firstOrFail();

        $expense = Expense::create([
            'organization_id' => $organization->id,
            'financial_account_id' => $account->id,
            'ledger_account_id' => $expenseLedgerAccount->id,
            'amount_cents' => $amountCents,
            'description' => $description,
            'expense_date' => $expenseDate,
            'recorded_by' => $actor->id,
            'status' => Expense::STATUS_POSTED,
        ]);

        $entry = $this->poster->post(
            $organization,
            "Expense: {$expense->description}",
            [
                ['ledger_account_id' => $expenseLedgerAccount->id, 'debit_cents' => $amountCents],
                ['ledger_account_id' => $account->ledgerAccount->id, 'credit_cents' => $amountCents],
            ],
            $actor,
            source: $expense,
            postedAt: $expense->expense_date->toImmutable()->startOfDay(),
        );

        $expense->update(['journal_entry_id' => $entry->id]);

        $this->auditLogger->log(
            'expense.recorded',
            $expense,
            organizationId: $organization->id,
            userId: $actor->id,
            after: $expense->only(['amount_cents', 'description', 'financial_account_id']),
        );

        return $expense;
    }

    public function reverse(Organization $organization, Expense $expense, User $actor): Expense
    {
        abort_unless($expense->organization_id === $organization->id, 404);
        abort_unless($expense->status === Expense::STATUS_POSTED, 422, 'This expense has already been reversed.');

        $this->poster->reverse($expense->journalEntry, $actor, "Reversal of expense: {$expense->description}");

        $expense->update(['status' => Expense::STATUS_REVERSED]);

        $this->auditLogger->log('expense.reversed', $expense->fresh(), organizationId: $organization->id, userId: $actor->id);

        return $expense;
    }
}
