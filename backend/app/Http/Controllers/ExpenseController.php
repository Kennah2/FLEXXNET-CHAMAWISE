<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Organization $organization): View
    {
        $this->authorize('viewAny', [Expense::class, $organization]);

        $expenses = $organization->expenses()
            ->with(['financialAccount', 'recorder'])
            ->orderByDesc('expense_date')
            ->paginate(30);

        $accounts = $organization->financialAccounts()->where('is_active', true)->orderBy('name')->get();

        return view('organizations.expenses.index', compact('organization', 'expenses', 'accounts'));
    }

    public function store(
        StoreExpenseRequest $request,
        Organization $organization,
        LedgerProvisioner $provisioner,
        LedgerPoster $poster,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $provisioner->provisionSystemAccounts($organization);

        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));
        $amountCents = Money::parseToCents($request->validated('amount'));
        $expenseLedgerAccount = LedgerAccount::where('organization_id', $organization->id)
            ->where('code', LedgerAccount::CODE_GENERAL_EXPENSE)
            ->firstOrFail();

        $expense = Expense::create([
            'organization_id' => $organization->id,
            'financial_account_id' => $account->id,
            'ledger_account_id' => $expenseLedgerAccount->id,
            'amount_cents' => $amountCents,
            'description' => $request->validated('description'),
            'expense_date' => $request->validated('expense_date'),
            'recorded_by' => $request->user()->id,
            'status' => Expense::STATUS_POSTED,
        ]);

        $entry = $poster->post(
            $organization,
            "Expense: {$expense->description}",
            [
                ['ledger_account_id' => $expenseLedgerAccount->id, 'debit_cents' => $amountCents],
                ['ledger_account_id' => $account->ledgerAccount->id, 'credit_cents' => $amountCents],
            ],
            $request->user(),
            source: $expense,
            postedAt: $expense->expense_date->toImmutable()->startOfDay(),
        );

        $expense->update(['journal_entry_id' => $entry->id]);

        $auditLogger->log(
            'expense.recorded',
            $expense,
            organizationId: $organization->id,
            after: $expense->only(['amount_cents', 'description', 'financial_account_id']),
        );

        return back()->with('status', 'Expense recorded.');
    }

    public function reverse(Organization $organization, Expense $expense, LedgerPoster $poster, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($expense->organization_id === $organization->id, 404);
        $this->authorize('reverse', $expense);
        abort_unless($expense->status === Expense::STATUS_POSTED, 422, 'This expense has already been reversed.');

        $poster->reverse($expense->journalEntry, request()->user(), "Reversal of expense: {$expense->description}");

        $expense->update(['status' => Expense::STATUS_REVERSED]);

        $auditLogger->log('expense.reversed', $expense->fresh(), organizationId: $organization->id);

        return back()->with('status', 'Expense reversed.');
    }
}
