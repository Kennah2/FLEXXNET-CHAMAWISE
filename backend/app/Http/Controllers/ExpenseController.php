<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Services\Expenses\ExpenseService;
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

    public function store(StoreExpenseRequest $request, Organization $organization, ExpenseService $service): RedirectResponse
    {
        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));

        $service->record(
            $organization,
            $account,
            Money::parseToCents($request->validated('amount')),
            $request->validated('description'),
            $request->validated('expense_date'),
            $request->user(),
        );

        return back()->with('status', 'Expense recorded.');
    }

    public function reverse(Organization $organization, Expense $expense, ExpenseService $service): RedirectResponse
    {
        abort_unless($expense->organization_id === $organization->id, 404);
        $this->authorize('reverse', $expense);

        $service->reverse($organization, $expense, request()->user());

        return back()->with('status', 'Expense reversed.');
    }
}
