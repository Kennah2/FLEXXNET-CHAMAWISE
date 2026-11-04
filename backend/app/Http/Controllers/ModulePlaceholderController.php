<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\View\View;

/**
 * Renders an honest "not built yet" page for sidebar modules the specification describes but
 * this phase hasn't implemented (loans, accounts, transactions, reports, accounting, chama
 * settings — contributions moved to ContributionController in Phase 2). Per the project's
 * strict development rules, a nav item must never be a dead link or silently pretend a feature
 * exists — this page says plainly what the module will do and which phase builds it, instead of
 * a 404 or a fabricated empty dashboard.
 */
class ModulePlaceholderController extends Controller
{
    private const MODULES = [
        'loans' => [
            'title' => 'Loans',
            'icon' => 'calculator',
            'phase' => 'Phase 6 — Advanced Chama Modules',
            'description' => 'Loan applications, approvals, interest and repayment schedules.',
        ],
        'accounts' => [
            'title' => 'Accounts',
            'icon' => 'card',
            'phase' => 'Phase 3 — Financial Integrity',
            'description' => 'Cash, bank and mobile money accounts backed by a proper ledger.',
        ],
        'transactions' => [
            'title' => 'Transactions',
            'icon' => 'list',
            'phase' => 'Phase 3 — Financial Integrity',
            'description' => 'The full transaction history, tied to the ledger.',
        ],
        'reports' => [
            'title' => 'Reports',
            'icon' => 'chart',
            'phase' => 'Phase 2 onward',
            'description' => 'Statements and summaries, exportable to PDF, Excel and CSV.',
        ],
        'accounting' => [
            'title' => 'Accounting',
            'icon' => 'book',
            'phase' => 'Phase 3 — Financial Integrity',
            'description' => 'The double-entry ledger, journal entries and reconciliation.',
        ],
        'settings' => [
            'title' => 'Chama Settings',
            'icon' => 'cog',
            'phase' => 'Phase 2',
            'description' => 'Contribution rules, approval thresholds and penalty policy.',
        ],
    ];

    public function show(Organization $organization, string $module): View
    {
        $this->authorize('view', $organization);

        abort_unless(array_key_exists($module, self::MODULES), 404);

        return view('organizations.coming-soon', [
            'organization' => $organization,
            'module' => $module,
            ...self::MODULES[$module],
        ]);
    }
}
