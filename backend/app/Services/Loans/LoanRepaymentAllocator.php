<?php

namespace App\Services\Loans;

use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentAllocation;
use Illuminate\Support\Facades\DB;

/**
 * Applies a loan repayment against the loan's outstanding installments, oldest first (FIFO) —
 * the same principle ContributionAllocator applies to contributions. Within each installment,
 * interest is paid down before principal (standard loan-servicing convention), not because this
 * app invented a lending rule, but because it has to pick a deterministic order and this is the
 * conventional one.
 *
 * LoanService validates a repayment can never exceed the loan's total outstanding before this
 * runs, so there is never a surplus left to decide what to do with here — every cent of a
 * repayment is allocated to something.
 */
class LoanRepaymentAllocator
{
    /**
     * @return array{principal_cents: int, interest_cents: int} What was actually allocated, in
     *                                                          total, across every installment —
     *                                                          the caller posts the ledger from this.
     */
    public function allocate(LoanRepayment $repayment): array
    {
        return DB::transaction(function () use ($repayment) {
            $remaining = $repayment->amount_cents;
            $totalPrincipal = 0;
            $totalInterest = 0;

            $installments = LoanInstallment::where('loan_id', $repayment->loan_id)
                ->whereIn('status', [LoanInstallment::STATUS_PENDING, LoanInstallment::STATUS_PARTIAL, LoanInstallment::STATUS_OVERDUE])
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            foreach ($installments as $installment) {
                if ($remaining <= 0) {
                    break;
                }

                $outstandingInterest = max(0, $installment->interest_due_cents - $installment->interest_paid_cents);
                $outstandingPrincipal = max(0, $installment->principal_due_cents - $installment->principal_paid_cents);

                $interestPortion = min($remaining, $outstandingInterest);
                $remaining -= $interestPortion;

                $principalPortion = min($remaining, $outstandingPrincipal);
                $remaining -= $principalPortion;

                if ($interestPortion <= 0 && $principalPortion <= 0) {
                    continue;
                }

                LoanRepaymentAllocation::create([
                    'loan_repayment_id' => $repayment->id,
                    'loan_installment_id' => $installment->id,
                    'principal_cents' => $principalPortion,
                    'interest_cents' => $interestPortion,
                ]);

                $installment->principal_paid_cents += $principalPortion;
                $installment->interest_paid_cents += $interestPortion;
                $installment->status = $installment->isFullyPaid()
                    ? LoanInstallment::STATUS_PAID
                    : LoanInstallment::STATUS_PARTIAL;
                $installment->save();

                $totalPrincipal += $principalPortion;
                $totalInterest += $interestPortion;
            }

            return ['principal_cents' => $totalPrincipal, 'interest_cents' => $totalInterest];
        });
    }

    /**
     * Reverses every allocation a repayment made, restoring each affected installment's paid
     * amounts and status. The allocation rows are kept and stamped with reversed_at rather than
     * deleted (Part 12).
     */
    public function reverse(LoanRepayment $repayment): void
    {
        DB::transaction(function () use ($repayment) {
            $allocations = $repayment->allocations()->whereNull('reversed_at')->lockForUpdate()->get();

            foreach ($allocations as $allocation) {
                $installment = $allocation->installment()->lockForUpdate()->first();
                $installment->principal_paid_cents = max(0, $installment->principal_paid_cents - $allocation->principal_cents);
                $installment->interest_paid_cents = max(0, $installment->interest_paid_cents - $allocation->interest_cents);
                $installment->status = $installment->totalPaidCents() <= 0
                    ? LoanInstallment::STATUS_PENDING
                    : ($installment->isFullyPaid() ? LoanInstallment::STATUS_PAID : LoanInstallment::STATUS_PARTIAL);
                $installment->save();

                $allocation->reversed_at = now();
                $allocation->save();
            }
        });
    }
}
