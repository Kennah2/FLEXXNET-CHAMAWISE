<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\View\View;

/**
 * Renders an honest "not built yet" page for sidebar modules the specification describes but
 * this phase hasn't implemented (loans, reports, chama settings — contributions moved to
 * ContributionController in Phase 2; accounts/transactions/accounting moved to their own
 * controllers in Phase 3). Per the project's strict development rules, a nav item must never be
 * a dead link or silently pretend a feature exists — this page says plainly what the module will
 * do and which phase builds it, instead of a 404 or a fabricated empty dashboard.
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
        'reports' => [
            'title' => 'Reports',
            'icon' => 'chart',
            'phase' => 'A later phase',
            'description' => 'Exportable statements and summaries (PDF, Excel, CSV).',
        ],
        'settings' => [
            'title' => 'Chama Settings',
            'icon' => 'cog',
            'phase' => 'A later phase',
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
