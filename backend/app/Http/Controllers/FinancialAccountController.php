<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancialAccountRequest;
use App\Http\Requests\StoreReconciliationRequest;
use App\Models\AccountReconciliation;
use App\Models\FinancialAccount;
use App\Models\LedgerPosting;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Services\Ledger\LedgerProvisioner;
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

    public function store(
        StoreFinancialAccountRequest $request,
        Organization $organization,
        LedgerProvisioner $provisioner,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $provisioner->provisionSystemAccounts($organization);

        $isFirstAccount = ! $organization->financialAccounts()->exists();

        $account = FinancialAccount::create([
            'organization_id' => $organization->id,
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'account_number' => $request->validated('account_number'),
            'currency' => $organization->currency,
            'opening_balance_cents' => Money::parseToCents($request->validated('opening_balance') ?? '0'),
            'is_default' => $isFirstAccount || $request->boolean('is_default'),
            'created_by' => $request->user()->id,
        ]);

        if ($account->is_default) {
            $organization->financialAccounts()->where('id', '!=', $account->id)->update(['is_default' => false]);
        }

        $provisioner->provisionForFinancialAccount($account);

        $auditLogger->log(
            'financial_account.created',
            $account,
            organizationId: $organization->id,
            after: $account->only(['name', 'type', 'opening_balance_cents']),
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

    public function reconcile(StoreReconciliationRequest $request, Organization $organization, FinancialAccount $account, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($account->organization_id === $organization->id, 404);

        $statementBalanceCents = Money::parseToCents($request->validated('statement_balance'));
        $computedBalanceCents = $account->balanceCents();

        $reconciliation = AccountReconciliation::create([
            'organization_id' => $organization->id,
            'financial_account_id' => $account->id,
            'statement_date' => $request->validated('statement_date'),
            'statement_balance_cents' => $statementBalanceCents,
            'computed_balance_cents' => $computedBalanceCents,
            'variance_cents' => $statementBalanceCents - $computedBalanceCents,
            'notes' => $request->validated('notes'),
            'recorded_by' => $request->user()->id,
        ]);

        $auditLogger->log(
            'account_reconciliation.recorded',
            $reconciliation,
            organizationId: $organization->id,
            after: $reconciliation->only(['statement_balance_cents', 'computed_balance_cents', 'variance_cents']),
        );

        $status = $reconciliation->isBalanced()
            ? 'Reconciled — the statement matches the ledger.'
            : 'Reconciliation recorded with a variance of '.Money::format(abs($reconciliation->variance_cents), $account->currency).'.';

        return back()->with('status', $status);
    }
}
