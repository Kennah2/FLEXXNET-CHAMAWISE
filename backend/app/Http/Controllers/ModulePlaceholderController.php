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
            'description' => 'Loan applications, guarantor approval, flat-rate and reducing-balance interest, repayment schedules, and loan statements.',
        ],
        'accounts' => [
            'title' => 'Accounts',
            'icon' => 'card',
            'phase' => 'Phase 3 — Financial Integrity',
            'description' => 'The chama\'s financial accounts (cash, bank, mobile money) backed by a proper double-entry ledger — never a number that can drift from reality.',
        ],
        'transactions' => [
            'title' => 'Transactions',
            'icon' => 'list',
            'phase' => 'Phase 3 — Financial Integrity',
            'description' => 'The full, auditable transaction history: contributions, withdrawals, expenses, transfers and adjustments, each tied to a verified ledger entry.',
        ],
        'reports' => [
            'title' => 'Reports',
            'icon' => 'chart',
            'phase' => 'Phase 2 onward',
            'description' => 'Member statements, cashbooks, contribution summaries and financial statements, exportable to PDF, Excel and CSV.',
        ],
        'accounting' => [
            'title' => 'Accounting',
            'icon' => 'book',
            'phase' => 'Phase 3 — Financial Integrity',
            'description' => 'The chama\'s double-entry ledger: journal entries, reconciliation, and a clear split between member savings liabilities and available cash.',
        ],
        'settings' => [
            'title' => 'Chama Settings',
            'icon' => 'cog',
            'phase' => 'Phase 2',
            'description' => 'Editable contribution rules, approval thresholds, penalty policy and other chama-specific configuration from Part 8 of the specification.',
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
