<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancialAccountRequest;
use App\Http\Requests\StoreReconciliationRequest;
use App\Models\FinancialAccount;
use App\Models\LedgerPosting;
use App\Models\Organization;
use App\Services\Accounts\FinancialAccountService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public function index(Organization $organization): View
    {
        $this->authorize('viewAny', [FinancialAccount::class, $organization]);

        $accounts = $organization->financialAccounts()->orderByDesc('is_default')->orderBy('name')->get();

        return view('organizations.accounts.index', compact('organization', 'accounts'));
    }

    public function create(Organization $organization): View
    {
        $this->authorize('create', [FinancialAccount::class, $organization]);

        return view('organizations.accounts.create', compact('organization'));
    }

    public function store(StoreFinancialAccountRequest $request, Organization $organization, FinancialAccountService $service): RedirectResponse
    {
        $account = $service->create(
            $organization,
            $request->validated('name'),
            $request->validated('type'),
            $request->validated('account_number'),
            Money::parseToCents($request->validated('opening_balance') ?? '0'),
            $request->boolean('is_default'),
            $request->user(),
        );

        return redirect()->route('organizations.accounts.show', [$organization, $account])
            ->with('status', "Account \"{$account->name}\" created.");
    }

    public function show(Organization $organization, FinancialAccount $account): View
    {
        abort_unless($account->organization_id === $organization->id, 404);
        $this->authorize('view', $account);

        $postings = LedgerPosting::query()
            ->where('ledger_account_id', $account->ledgerAccount?->id)
            ->with(['journalEntry'])
            ->orderByDesc('created_at')
            ->paginate(30);

        $reconciliations = $account->reconciliations()->orderByDesc('statement_date')->limit(10)->get();

        return view('organizations.accounts.show', compact('organization', 'account', 'postings', 'reconciliations'));
    }

    public function reconcile(StoreReconciliationRequest $request, Organization $organization, FinancialAccount $account, FinancialAccountService $service): RedirectResponse
    {
        $reconciliation = $service->reconcile(
            $organization,
            $account,
            $request->validated('statement_date'),
            Money::parseToCents($request->validated('statement_balance')),
            $request->validated('notes'),
            $request->user(),
        );

        $status = $reconciliation->isBalanced()
            ? 'Reconciled — the statement matches the ledger.'
            : 'Reconciliation recorded with a variance of '.Money::format(abs($reconciliation->variance_cents), $account->currency).'.';

        return back()->with('status', $status);
    }
}
