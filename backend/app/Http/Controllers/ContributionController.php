<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContributionRequest;
use App\Models\Contribution;
use App\Models\ContributionObligation;
use App\Models\ContributionSchedule;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Contributions\ContributionAllocator;
use App\Services\Contributions\ObligationGenerator;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;
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

    public function store(StoreContributionRequest $request, Organization $organization, AuditLogger $auditLogger): RedirectResponse
    {
        $forUserId = (int) ($request->validated('user_id') ?: $request->user()->id);

        $contribution = Contribution::create([
            'organization_id' => $organization->id,
            'user_id' => $forUserId,
            'contribution_schedule_id' => $request->validated('contribution_schedule_id'),
            'amount_cents' => Money::parseToCents($request->validated('amount')),
            'currency' => $organization->currency,
            'payment_method' => $request->validated('payment_method'),
            'transaction_reference' => $request->validated('transaction_reference'),
            'paid_at' => $request->validated('paid_at'),
            'recorded_by' => $request->user()->id,
            'status' => Contribution::STATUS_PENDING_VERIFICATION,
            'notes' => $request->validated('notes'),
        ]);

        $auditLogger->log(
            'contribution.recorded',
            $contribution,
            organizationId: $organization->id,
            after: $contribution->only(['user_id', 'amount_cents', 'payment_method']),
        );

        return back()->with('status', 'Contribution recorded and awaiting verification.');
    }

    public function verify(
        Request $request,
        Organization $organization,
        Contribution $contribution,
        ContributionAllocator $allocator,
        ObligationGenerator $generator,
        LedgerPoster $poster,
        LedgerProvisioner $ledgerProvisioner,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless($contribution->organization_id === $organization->id, 404);
        $this->authorize('verify', $contribution);

        abort_unless($contribution->status === Contribution::STATUS_PENDING_VERIFICATION, 422, 'Only a pending contribution can be verified.');

        $ledgerProvisioner->provisionSystemAccounts($organization);

        $validated = $request->validate([
            'financial_account_id' => ['required', 'integer'],
        ]);
        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($validated['financial_account_id']);
        $ledgerProvisioner->provisionForFinancialAccount($account);

        // Obligations are otherwise only generated lazily when someone views the contributions
        // page (index()) — verifying a payment must not depend on that having happened recently,
        // so make sure the schedule's obligations are current before allocating against them.
        if ($contribution->contribution_schedule_id) {
            $generator->generate($contribution->schedule, CarbonImmutable::now());
        }

        if ($contribution->transaction_reference) {
            $duplicate = Contribution::query()
                ->where('organization_id', $organization->id)
                ->where('transaction_reference', $contribution->transaction_reference)
                ->where('status', Contribution::STATUS_VERIFIED)
                ->where('id', '!=', $contribution->id)
                ->exists();

            abort_if($duplicate, 422, 'This transaction reference has already been verified on another contribution.');
        }

        $savingsAccount = $organization->memberSavingsAccount();

        $entry = $poster->post(
            $organization,
            "Contribution verified: {$contribution->user->name}",
            [
                ['ledger_account_id' => $account->ledgerAccount->id, 'debit_cents' => $contribution->amount_cents],
                ['ledger_account_id' => $savingsAccount->id, 'credit_cents' => $contribution->amount_cents, 'user_id' => $contribution->user_id],
            ],
            $request->user(),
            source: $contribution,
        );

        $contribution->status = Contribution::STATUS_VERIFIED;
        $contribution->verified_by = $request->user()->id;
        $contribution->verified_at = now();
        $contribution->financial_account_id = $account->id;
        $contribution->journal_entry_id = $entry->id;
        $contribution->save();

        $allocator->allocate($contribution);

        $auditLogger->log('contribution.verified', $contribution, organizationId: $organization->id);

        return back()->with('status', 'Contribution verified, posted to the ledger, and allocated.');
    }

    public function reject(Organization $organization, Contribution $contribution, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        $this->authorize('reject', $contribution);

        abort_unless($contribution->status === Contribution::STATUS_PENDING_VERIFICATION, 422, 'Only a pending contribution can be rejected.');

        $contribution->status = Contribution::STATUS_REJECTED;
        $contribution->verified_by = request()->user()->id;
        $contribution->verified_at = now();
        $contribution->save();

        $auditLogger->log('contribution.rejected', $contribution, organizationId: $organization->id);

        return back()->with('status', 'Contribution rejected.');
    }

    public function reverse(
        Organization $organization,
        Contribution $contribution,
        ContributionAllocator $allocator,
        LedgerPoster $poster,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless($contribution->organization_id === $organization->id, 404);
        $this->authorize('reverse', $contribution);

        abort_unless($contribution->status === Contribution::STATUS_VERIFIED, 422, 'Only a verified contribution can be reversed.');

        $allocator->reverse($contribution);

        if ($contribution->journal_entry_id) {
            $poster->reverse($contribution->journalEntry, request()->user(), "Reversal of contribution: {$contribution->user->name}");
        }

        $contribution->status = Contribution::STATUS_REVERSED;
        $contribution->save();

        $auditLogger->log('contribution.reversed', $contribution, organizationId: $organization->id);

        return back()->with('status', 'Contribution reversed.');
    }
}
