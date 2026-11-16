<?php

namespace App\Services\Loans;

use App\Models\FinancialAccount;
use App\Models\LedgerAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;
use Carbon\CarbonImmutable;

/**
 * Every write action on a Loan, from application through to a fully-repaid (or reversed)
 * closure — built as a service from the start (unlike Phase 3/4's web controllers, which had to
 * be retrofitted into services in Phase 5 once the API needed the same logic; see
 * docs/DEVELOPMENT_PROGRESS.md's Phase 5 entry for why that retrofit happened and why this
 * module starts here instead).
 *
 * Approval sets the loan's actual terms (amount/rate/term may differ from what was requested);
 * disbursement is a separate, deliberate act that generates the installment schedule and posts
 * the ledger — mirroring how Phase 3 separates a withdrawal's approval from its payment.
 */
class LoanService
{
    public function __construct(
        private readonly LoanInstallmentScheduleGenerator $scheduleGenerator,
        private readonly LoanRepaymentAllocator $repaymentAllocator,
        private readonly LedgerPoster $poster,
        private readonly LedgerProvisioner $provisioner,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function apply(
        Organization $organization,
        int $forUserId,
        int $requestedAmountCents,
        int $requestedTermMonths,
        ?string $reason,
        User $actor,
    ): Loan {
        $loan = Loan::create([
            'organization_id' => $organization->id,
            'user_id' => $forUserId,
            'requested_by' => $actor->id,
            'requested_amount_cents' => $requestedAmountCents,
            'requested_term_months' => $requestedTermMonths,
            'reason' => $reason,
            'status' => Loan::STATUS_PENDING,
        ]);

        $this->auditLogger->log(
            'loan.applied',
            $loan,
            organizationId: $organization->id,
            userId: $actor->id,
            after: $loan->only(['user_id', 'requested_amount_cents', 'requested_term_months']),
        );

        return $loan;
    }

    public function approve(
        Organization $organization,
        Loan $loan,
        int $approvedAmountCents,
        int $termMonths,
        int $interestRateBps,
        User $actor,
    ): Loan {
        abort_unless($loan->organization_id === $organization->id, 404);
        abort_unless($loan->status === Loan::STATUS_PENDING, 422, 'Only a pending loan application can be approved.');

        $loan->update([
            'status' => Loan::STATUS_APPROVED,
            'approved_amount_cents' => $approvedAmountCents,
            'term_months' => $termMonths,
            'interest_rate_bps' => $interestRateBps,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $this->auditLogger->log('loan.approved', $loan->fresh(), organizationId: $organization->id, userId: $actor->id);

        return $loan->fresh();
    }

    public function reject(Organization $organization, Loan $loan, User $actor): Loan
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        abort_unless($loan->status === Loan::STATUS_PENDING, 422, 'Only a pending loan application can be rejected.');

        $loan->update(['status' => Loan::STATUS_REJECTED]);

        $this->auditLogger->log('loan.rejected', $loan, organizationId: $organization->id, userId: $actor->id);

        return $loan;
    }

    public function cancel(Organization $organization, Loan $loan, User $actor): Loan
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        abort_unless($loan->status === Loan::STATUS_PENDING, 422, 'Only a pending loan application can be cancelled.');

        $loan->update(['status' => Loan::STATUS_CANCELLED]);

        $this->auditLogger->log('loan.cancelled', $loan, organizationId: $organization->id, userId: $actor->id);

        return $loan;
    }

    public function disburse(Organization $organization, Loan $loan, FinancialAccount $account, User $actor): Loan
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        abort_unless($loan->status === Loan::STATUS_APPROVED, 422, 'Only an approved loan can be disbursed.');

        $this->provisioner->provisionSystemAccounts($organization);
        $this->provisioner->provisionForFinancialAccount($account);

        // A loan being approved does not mean the chama actually has the cash on hand right now
        // to pay it out — the same "re-check the real balance at the moment money actually
        // moves" principle WithdrawalService::pay() applies, since an account's balance can
        // have moved between a loan's approval and its disbursement.
        abort_if(
            $loan->approved_amount_cents > $account->balanceCents(),
            422,
            "{$account->name} does not have enough balance to disburse this loan.",
        );

        $loansReceivable = $organization->loansReceivableAccount();

        $entry = $this->poster->post(
            $organization,
            "Loan disbursed to {$loan->user->name}",
            [
                ['ledger_account_id' => $loansReceivable->id, 'debit_cents' => $loan->approved_amount_cents, 'user_id' => $loan->user_id],
                ['ledger_account_id' => $account->ledgerAccount->id, 'credit_cents' => $loan->approved_amount_cents],
            ],
            $actor,
            source: $loan,
        );

        $loan->update([
            'status' => Loan::STATUS_DISBURSED,
            'financial_account_id' => $account->id,
            'disbursed_by' => $actor->id,
            'disbursed_at' => now(),
            'journal_entry_id' => $entry->id,
        ]);

        $this->scheduleGenerator->generate($loan->fresh(), CarbonImmutable::now());

        $this->auditLogger->log('loan.disbursed', $loan->fresh(), organizationId: $organization->id, userId: $actor->id);

        return $loan->fresh();
    }

    public function recordRepayment(Organization $organization, Loan $loan, FinancialAccount $account, int $amountCents, User $actor): LoanRepayment
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        abort_unless($loan->status === Loan::STATUS_DISBURSED, 422, 'Only a disbursed loan can receive repayments.');

        $loan->load('installments');
        $outstanding = $loan->outstandingPrincipalCents() + $loan->outstandingInterestCents();

        abort_if($amountCents > $outstanding, 422, 'This repayment is more than the loan\'s outstanding balance.');

        $this->provisioner->provisionSystemAccounts($organization);
        $this->provisioner->provisionForFinancialAccount($account);

        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'organization_id' => $organization->id,
            'amount_cents' => $amountCents,
            'paid_at' => CarbonImmutable::now(),
            'financial_account_id' => $account->id,
            'recorded_by' => $actor->id,
            'status' => LoanRepayment::STATUS_POSTED,
        ]);

        $allocated = $this->repaymentAllocator->allocate($repayment);

        $loansReceivable = $organization->loansReceivableAccount();
        $interestIncome = $organization->ledgerAccounts()->where('code', LedgerAccount::CODE_INTEREST_INCOME)->first();

        $lines = [
            ['ledger_account_id' => $account->ledgerAccount->id, 'debit_cents' => $amountCents],
        ];
        if ($allocated['principal_cents'] > 0) {
            $lines[] = ['ledger_account_id' => $loansReceivable->id, 'credit_cents' => $allocated['principal_cents'], 'user_id' => $loan->user_id];
        }
        if ($allocated['interest_cents'] > 0) {
            $lines[] = ['ledger_account_id' => $interestIncome->id, 'credit_cents' => $allocated['interest_cents']];
        }

        $entry = $this->poster->post(
            $organization,
            "Loan repayment: {$loan->user->name}",
            $lines,
            $actor,
            source: $repayment,
        );

        $repayment->update([
            'principal_cents' => $allocated['principal_cents'],
            'interest_cents' => $allocated['interest_cents'],
            'journal_entry_id' => $entry->id,
        ]);

        if ($loan->fresh()->load('installments')->isFullyRepaid()) {
            $loan->update(['status' => Loan::STATUS_COMPLETED]);
        }

        $this->auditLogger->log(
            'loan.repayment_recorded',
            $repayment,
            organizationId: $organization->id,
            userId: $actor->id,
            after: $repayment->only(['amount_cents', 'principal_cents', 'interest_cents']),
        );

        return $repayment->fresh();
    }

    public function reverseRepayment(Organization $organization, LoanRepayment $repayment, User $actor): LoanRepayment
    {
        abort_unless($repayment->organization_id === $organization->id, 404);
        abort_unless($repayment->status === LoanRepayment::STATUS_POSTED, 422, 'This repayment has already been reversed.');

        $this->repaymentAllocator->reverse($repayment);

        if ($repayment->journal_entry_id) {
            $this->poster->reverse($repayment->journalEntry, $actor, "Reversal of loan repayment: {$repayment->loan->user->name}");
        }

        $repayment->update(['status' => LoanRepayment::STATUS_REVERSED]);

        $loan = $repayment->loan;
        if ($loan->status === Loan::STATUS_COMPLETED) {
            $loan->update(['status' => Loan::STATUS_DISBURSED]);
        }

        $this->auditLogger->log('loan.repayment_reversed', $repayment->fresh(), organizationId: $organization->id, userId: $actor->id);

        return $repayment->fresh();
    }
}
