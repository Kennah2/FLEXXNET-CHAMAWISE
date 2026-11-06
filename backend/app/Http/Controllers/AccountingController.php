<?php

namespace App\Http\Controllers;

use App\Models\LedgerPosting;
use App\Models\Organization;
use App\Services\Ledger\LedgerProvisioner;
use Illuminate\View\View;

class AccountingController extends Controller
{
    /**
     * The chama's chart of accounts and a trial balance — every ledger account's current
     * balance, grouped by type, plus a system-wide check that total debits equal total credits
     * (if they don't, something bypassed LedgerPoster, which should be structurally impossible).
     */
    public function index(Organization $organization, LedgerProvisioner $provisioner): View
    {
        $this->authorize('view', $organization);

        $provisioner->provisionSystemAccounts($organization);

        $accounts = $organization->ledgerAccounts()->orderBy('type')->orderBy('name')->get();
        $byType = $accounts->groupBy('type');

        $totals = LedgerPosting::where('organization_id', $organization->id)
            ->selectRaw('COALESCE(SUM(debit_cents), 0) as debits, COALESCE(SUM(credit_cents), 0) as credits')
            ->first();

        return view('organizations.accounting.index', [
            'organization' => $organization,
            'byType' => $byType,
            'totalDebits' => (int) $totals->debits,
            'totalCredits' => (int) $totals->credits,
        ]);
    }
}
