<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContributionRequest;
use App\Models\Contribution;
use App\Models\ContributionObligation;
use App\Models\ContributionSchedule;
use App\Models\Organization;
use App\Models\User;
use App\Services\Contributions\ContributionService;
use App\Services\Contributions\ObligationGenerator;
use App\Support\ChamaPermissions;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class ContributionController extends Controller
{
    public function index(Organization $organization, ObligationGenerator $generator): View
    {
        $this->authorize('viewAny', [ContributionSchedule::class, $organization]);

        $activeSchedules = $organization->contributionSchedules()->where('status', ContributionSchedule::STATUS_ACTIVE)->get();
        foreach ($activeSchedules as $schedule) {
            $generator->generate($schedule, CarbonImmutable::now());
        }

        $user = request()->user();

        $myObligations = ContributionObligation::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->with('schedule')
            ->orderByDesc('period_start')
            ->limit(30)
            ->get();

        $myOutstandingObligations = ContributionObligation::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [ContributionObligation::STATUS_PENDING, ContributionObligation::STATUS_PARTIAL, ContributionObligation::STATUS_OVERDUE])
            ->get();

        $myOutstandingCents = $myOutstandingObligations->sum(fn (ContributionObligation $o) => $o->outstandingCents());

        // Tracked and shown separately from the contribution itself — "the missed contribution
        // remains outstanding separately from the penalty" (Part 11).
        $myOutstandingPenaltyCents = $myOutstandingObligations
            ->where('penalty_status', ContributionObligation::PENALTY_ASSESSED)
            ->sum('penalty_amount_cents');

        $myPayments = Contribution::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $canManageFinances = User::find($user->id)->can(ChamaPermissions::MANAGE_FINANCES);

        $pendingVerifications = collect();
        $organizationTotalVerifiedCents = 0;

        if ($canManageFinances) {
            $pendingVerifications = Contribution::query()
                ->where('organization_id', $organization->id)
                ->where('status', Contribution::STATUS_PENDING_VERIFICATION)
                ->with('user')
                ->orderBy('paid_at')
                ->get();

            $organizationTotalVerifiedCents = Contribution::query()
                ->where('organization_id', $organization->id)
                ->where('status', Contribution::STATUS_VERIFIED)
                ->sum('amount_cents');
        }

        $members = $organization->members()->orderBy('name')->get();
        $accounts = $canManageFinances
            ? $organization->financialAccounts()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get()
            : collect();

        $darajaCredential = $organization->darajaCredential();
        $mpesaEnabled = (bool) ($darajaCredential?->is_enabled && $darajaCredential->isConfigured());

        return view('organizations.contributions.index', compact(
            'organization',
            'activeSchedules',
            'myObligations',
            'myOutstandingCents',
            'myOutstandingPenaltyCents',
            'myPayments',
            'canManageFinances',
            'pendingVerifications',
            'organizationTotalVerifiedCents',
            'members',
            'accounts',
            'mpesaEnabled',
        ));
    }

    public function store(StoreContributionRequest $request, Organization $organization, ContributionService $service): RedirectResponse
    {
        $forUserId = (int) ($request->validated('user_id') ?: $request->user()->id);
        $scheduleId = $request->validated('contribution_schedule_id');

        $service->record(
            $organization,
            $forUserId,
            $scheduleId !== null ? (int) $scheduleId : null,
            Money::parseToCents($request->validated('amount')),
            $request->validated('payment_method'),
            $request->validated('transaction_reference'),
            $request->validated('paid_at'),
            $request->validated('notes'),
            $request->user(),
        );

        return back()->with('status', 'Contribution recorded and awaiting verification.');
    }

    public function verify(Request $request, Organization $organization, Contribution $contribution, ContributionService $service): RedirectResponse
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        $this->authorize('verify', $contribution);

        $validated = $request->validate([
            'financial_account_id' => ['required', 'integer'],
        ]);

        $service->verify($organization, $contribution, (int) $validated['financial_account_id'], $request->user());

        return back()->with('status', 'Contribution verified, posted to the ledger, and allocated.');
    }

    public function reject(Organization $organization, Contribution $contribution, ContributionService $service): RedirectResponse
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        $this->authorize('reject', $contribution);

        $service->reject($organization, $contribution, request()->user());

        return back()->with('status', 'Contribution rejected.');
    }

    public function reverse(Organization $organization, Contribution $contribution, ContributionService $service): RedirectResponse
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        $this->authorize('reverse', $contribution);

        $service->reverse($organization, $contribution, request()->user());

        return back()->with('status', 'Contribution reversed.');
    }
}
