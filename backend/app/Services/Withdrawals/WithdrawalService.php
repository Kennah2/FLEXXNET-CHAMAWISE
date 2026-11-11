<?php

namespace App\Services\Withdrawals;

use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Models\WithdrawalApproval;
use App\Models\WithdrawalRequest;
use App\Services\AuditLogger;
use App\Services\Ledger\LedgerPoster;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Every write action on a WithdrawalRequest, shared between the web controller and the v1 API
 * controller (Part 15) — requesting, approving, rejecting, paying out, cancelling, and reversing.
 * The policy checks (who may do what, including the hard self-approval block) stay in
 * WithdrawalRequestPolicy and are run by the caller before any of these methods are invoked —
 * this class only enforces state-machine and balance invariants that apply regardless of who is
 * calling it.
 */
class WithdrawalService
{
    public function __construct(
        private readonly LedgerPoster $poster,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function request(Organization $organization, FinancialAccount $account, int $amountCents, ?string $reason, User $actor): WithdrawalRequest
    {
        $savingsAccount = $organization->memberSavingsAccount();
        $availableCents = $savingsAccount->balanceCents($actor->id);

        abort_if($amountCents > $availableCents, 422, 'You cannot withdraw more than your current savings balance ('.Money::format($availableCents, $organization->currency).').');

        $requiredApprovals = max(1, (int) $organization->setting('withdrawals.required_approvals', 2));

        $withdrawal = WithdrawalRequest::create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'requested_by' => $actor->id,
            'financial_account_id' => $account->id,
            'amount_cents' => $amountCents,
            'reason' => $reason,
            'required_approvals' => $requiredApprovals,
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);

        $this->auditLogger->log(
            'withdrawal_request.created',
            $withdrawal,
            organizationId: $organization->id,
            userId: $actor->id,
            after: $withdrawal->only(['amount_cents', 'financial_account_id']),
        );

        return $withdrawal;
    }

    public function approve(Organization $organization, WithdrawalRequest $withdrawal, User $actor): WithdrawalRequest
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        abort_unless($withdrawal->status === WithdrawalRequest::STATUS_PENDING, 422, 'This request is no longer pending.');

        DB::transaction(function () use ($withdrawal, $actor) {
            WithdrawalApproval::create([
                'withdrawal_request_id' => $withdrawal->id,
                'user_id' => $actor->id,
                'decision' => WithdrawalApproval::DECISION_APPROVED,
            ]);

            if ($withdrawal->fresh()->isFullyApproved()) {
                $withdrawal->update(['status' => WithdrawalRequest::STATUS_APPROVED]);
            }
        });

        $this->auditLogger->log('withdrawal_request.approved', $withdrawal->fresh(), organizationId: $organization->id, userId: $actor->id);

        return $withdrawal->fresh();
    }

    public function reject(Organization $organization, WithdrawalRequest $withdrawal, ?string $reason, User $actor): WithdrawalRequest
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        abort_unless($withdrawal->status === WithdrawalRequest::STATUS_PENDING, 422, 'This request is no longer pending.');

        DB::transaction(function () use ($withdrawal, $reason, $actor) {
            WithdrawalApproval::create([
                'withdrawal_request_id' => $withdrawal->id,
                'user_id' => $actor->id,
                'decision' => WithdrawalApproval::DECISION_REJECTED,
                'reason' => $reason,
            ]);

            $withdrawal->update(['status' => WithdrawalRequest::STATUS_REJECTED]);
        });

        $this->auditLogger->log('withdrawal_request.rejected', $withdrawal->fresh(), organizationId: $organization->id, userId: $actor->id);

        return $withdrawal->fresh();
    }

    public function pay(Organization $organization, WithdrawalRequest $withdrawal, User $actor): WithdrawalRequest
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        abort_unless($withdrawal->status === WithdrawalRequest::STATUS_APPROVED, 422, 'Only an approved request can be paid.');

        $savingsAccount = $organization->memberSavingsAccount();
        $availableCents = $savingsAccount->balanceCents($withdrawal->user_id);

        abort_if($withdrawal->amount_cents > $availableCents, 422, "The member's balance has since dropped below this request's amount — cannot pay out.");

        $financialLedgerAccount = $withdrawal->financialAccount->ledgerAccount;

        $entry = $this->poster->post(
            $organization,
            "Withdrawal paid to {$withdrawal->user->name}",
            [
                ['ledger_account_id' => $savingsAccount->id, 'debit_cents' => $withdrawal->amount_cents, 'user_id' => $withdrawal->user_id],
                ['ledger_account_id' => $financialLedgerAccount->id, 'credit_cents' => $withdrawal->amount_cents],
            ],
            $actor,
            source: $withdrawal,
        );

        $withdrawal->update([
            'status' => WithdrawalRequest::STATUS_PAID,
            'paid_by' => $actor->id,
            'paid_at' => now(),
            'journal_entry_id' => $entry->id,
        ]);

        $this->auditLogger->log('withdrawal_request.paid', $withdrawal->fresh(), organizationId: $organization->id, userId: $actor->id);

        return $withdrawal->fresh();
    }

    public function cancel(Organization $organization, WithdrawalRequest $withdrawal, User $actor): WithdrawalRequest
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);

        $withdrawal->update(['status' => WithdrawalRequest::STATUS_CANCELLED]);

        $this->auditLogger->log('withdrawal_request.cancelled', $withdrawal, organizationId: $organization->id, userId: $actor->id);

        return $withdrawal->fresh();
    }

    public function reverse(Organization $organization, WithdrawalRequest $withdrawal, User $actor): WithdrawalRequest
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        abort_unless($withdrawal->status === WithdrawalRequest::STATUS_PAID, 422, 'Only a paid withdrawal can be reversed.');

        $this->poster->reverse($withdrawal->journalEntry, $actor, "Reversal of withdrawal paid to {$withdrawal->user->name}");

        $withdrawal->update(['status' => WithdrawalRequest::STATUS_REVERSED]);

        $this->auditLogger->log('withdrawal_request.reversed', $withdrawal->fresh(), organizationId: $organization->id, userId: $actor->id);

        return $withdrawal->fresh();
    }
}
