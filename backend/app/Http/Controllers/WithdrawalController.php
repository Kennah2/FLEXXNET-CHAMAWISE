<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWithdrawalRequestRequest;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Models\WithdrawalApproval;
use App\Models\WithdrawalRequest;
use App\Services\AuditLogger;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;
use App\Support\ChamaPermissions;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class WithdrawalController extends Controller
{
    public function index(Organization $organization): View
    {
        $this->authorize('viewAny', [WithdrawalRequest::class, $organization]);

        $user = request()->user();

        $myRequests = WithdrawalRequest::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->with(['financialAccount', 'approvals'])
            ->orderByDesc('created_at')
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $canManageFinances = User::find($user->id)->can(ChamaPermissions::MANAGE_FINANCES);

        $pendingApprovals = collect();
        $readyToPay = collect();
        if ($canManageFinances) {
            $pendingApprovals = WithdrawalRequest::where('organization_id', $organization->id)
                ->where('status', WithdrawalRequest::STATUS_PENDING)
                ->with(['user', 'financialAccount', 'approvals'])
                ->orderBy('created_at')
                ->get();

            // Every org-wide approved-but-unpaid request, not just the current user's own — a
            // treasurer must be able to pay out withdrawals they didn't personally request.
            $readyToPay = WithdrawalRequest::where('organization_id', $organization->id)
                ->where('status', WithdrawalRequest::STATUS_APPROVED)
                ->with(['user', 'financialAccount'])
                ->orderBy('created_at')
                ->get();
        }

        $accounts = $organization->financialAccounts()->where('is_active', true)->orderBy('name')->get();
        $savingsAccount = $organization->memberSavingsAccount();
        $myBalanceCents = $savingsAccount?->balanceCents($user->id) ?? 0;

        return view('organizations.withdrawals.index', compact(
            'organization',
            'myRequests',
            'canManageFinances',
            'pendingApprovals',
            'readyToPay',
            'accounts',
            'myBalanceCents',
        ));
    }

    public function store(
        StoreWithdrawalRequestRequest $request,
        Organization $organization,
        LedgerProvisioner $provisioner,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $provisioner->provisionSystemAccounts($organization);

        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));
        $amountCents = Money::parseToCents($request->validated('amount'));

        $savingsAccount = $organization->memberSavingsAccount();
        $availableCents = $savingsAccount->balanceCents($request->user()->id);

        abort_if($amountCents > $availableCents, 422, 'You cannot withdraw more than your current savings balance ('.Money::format($availableCents, $organization->currency).').');

        $requiredApprovals = max(1, (int) $organization->setting('withdrawals.required_approvals', 2));

        $withdrawal = WithdrawalRequest::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'requested_by' => $request->user()->id,
            'financial_account_id' => $account->id,
            'amount_cents' => $amountCents,
            'reason' => $request->validated('reason'),
            'required_approvals' => $requiredApprovals,
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);

        $auditLogger->log(
            'withdrawal_request.created',
            $withdrawal,
            organizationId: $organization->id,
            after: $withdrawal->only(['amount_cents', 'financial_account_id']),
        );

        return redirect()->route('organizations.withdrawals.index', $organization)
            ->with('status', 'Withdrawal request submitted — awaiting approval.');
    }

    public function approve(Organization $organization, WithdrawalRequest $withdrawal, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('approve', $withdrawal);
        abort_unless($withdrawal->status === WithdrawalRequest::STATUS_PENDING, 422, 'This request is no longer pending.');

        DB::transaction(function () use ($withdrawal) {
            WithdrawalApproval::create([
                'withdrawal_request_id' => $withdrawal->id,
                'user_id' => request()->user()->id,
                'decision' => WithdrawalApproval::DECISION_APPROVED,
            ]);

            if ($withdrawal->fresh()->isFullyApproved()) {
                $withdrawal->update(['status' => WithdrawalRequest::STATUS_APPROVED]);
            }
        });

        $auditLogger->log('withdrawal_request.approved', $withdrawal->fresh(), organizationId: $organization->id);

        return back()->with('status', 'Approval recorded.');
    }

    public function reject(Organization $organization, WithdrawalRequest $withdrawal, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('approve', $withdrawal);
        abort_unless($withdrawal->status === WithdrawalRequest::STATUS_PENDING, 422, 'This request is no longer pending.');

        DB::transaction(function () use ($withdrawal) {
            WithdrawalApproval::create([
                'withdrawal_request_id' => $withdrawal->id,
                'user_id' => request()->user()->id,
                'decision' => WithdrawalApproval::DECISION_REJECTED,
                'reason' => request('reason'),
            ]);

            $withdrawal->update(['status' => WithdrawalRequest::STATUS_REJECTED]);
        });

        $auditLogger->log('withdrawal_request.rejected', $withdrawal->fresh(), organizationId: $organization->id);

        return back()->with('status', 'Withdrawal request rejected.');
    }

    public function pay(Organization $organization, WithdrawalRequest $withdrawal, LedgerPoster $poster, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('pay', $withdrawal);
        abort_unless($withdrawal->status === WithdrawalRequest::STATUS_APPROVED, 422, 'Only an approved request can be paid.');

        $savingsAccount = $organization->memberSavingsAccount();
        $availableCents = $savingsAccount->balanceCents($withdrawal->user_id);

        abort_if($withdrawal->amount_cents > $availableCents, 422, "The member's balance has since dropped below this request's amount — cannot pay out.");

        $financialLedgerAccount = $withdrawal->financialAccount->ledgerAccount;

        $entry = $poster->post(
            $organization,
            "Withdrawal paid to {$withdrawal->user->name}",
            [
                ['ledger_account_id' => $savingsAccount->id, 'debit_cents' => $withdrawal->amount_cents, 'user_id' => $withdrawal->user_id],
                ['ledger_account_id' => $financialLedgerAccount->id, 'credit_cents' => $withdrawal->amount_cents],
            ],
            request()->user(),
            source: $withdrawal,
        );

        $withdrawal->update([
            'status' => WithdrawalRequest::STATUS_PAID,
            'paid_by' => request()->user()->id,
            'paid_at' => now(),
            'journal_entry_id' => $entry->id,
        ]);

        $auditLogger->log('withdrawal_request.paid', $withdrawal->fresh(), organizationId: $organization->id);

        return back()->with('status', 'Withdrawal paid and posted to the ledger.');
    }

    public function cancel(Organization $organization, WithdrawalRequest $withdrawal, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('cancel', $withdrawal);

        $withdrawal->update(['status' => WithdrawalRequest::STATUS_CANCELLED]);

        $auditLogger->log('withdrawal_request.cancelled', $withdrawal, organizationId: $organization->id);

        return back()->with('status', 'Withdrawal request cancelled.');
    }

    public function reverse(Organization $organization, WithdrawalRequest $withdrawal, LedgerPoster $poster, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('reverse', $withdrawal);
        abort_unless($withdrawal->status === WithdrawalRequest::STATUS_PAID, 422, 'Only a paid withdrawal can be reversed.');

        $poster->reverse($withdrawal->journalEntry, request()->user(), "Reversal of withdrawal paid to {$withdrawal->user->name}");

        $withdrawal->update(['status' => WithdrawalRequest::STATUS_REVERSED]);

        $auditLogger->log('withdrawal_request.reversed', $withdrawal->fresh(), organizationId: $organization->id);

        return back()->with('status', 'Withdrawal reversed.');
    }
}
