<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Carbon\CarbonImmutable;

/**
 * Builds a loan's full installment schedule, once, at disbursement time, using flat (simple)
 * interest over the loan's term — not reducing-balance amortization. This is a deliberate
 * simplification matching how chama lending is commonly done in practice (a flat rate applied to
 * the original principal, split evenly across the term) rather than a bank-style amortization
 * schedule; a future phase could add reducing-balance as an alternative, but should not assume
 * it silently replaces this (Part 36: report architectural decisions rather than let them be
 * implicit).
 *
 * Every monthly installment gets an equal share of principal and of total interest, **except the
 * last**, which absorbs whatever integer-division remainder is left — this guarantees the sum of
 * every installment's principal_due_cents exactly equals the disbursed principal (and likewise
 * for interest), with no cent silently gained or lost to rounding, the same "last one absorbs
 * the remainder" pattern this codebase already uses nowhere else only because nothing else divides
 * a total evenly across a fixed count of periods.
 */
class LoanInstallmentScheduleGenerator
{
    public function generate(Loan $loan, CarbonImmutable $disbursedAt): void
    {
        $principalCents = $loan->approved_amount_cents;
        $termMonths = $loan->term_months;
        $rateBps = $loan->interest_rate_bps;

        // Total interest over the full term, flat-rate: principal * (rate / 10000) * (term / 12).
        // Integer arithmetic throughout — never floats (docs/FINANCIAL_LEDGER.md).
        $totalInterestCents = intdiv($principalCents * $rateBps * $termMonths, 10_000 * 12);

        $basePrincipal = intdiv($principalCents, $termMonths);
        $baseInterest = intdiv($totalInterestCents, $termMonths);

        $allocatedPrincipal = 0;
        $allocatedInterest = 0;

        for ($number = 1; $number <= $termMonths; $number++) {
            $isLast = $number === $termMonths;
            $principalDue = $isLast ? ($principalCents - $allocatedPrincipal) : $basePrincipal;
            $interestDue = $isLast ? ($totalInterestCents - $allocatedInterest) : $baseInterest;

            LoanInstallment::create([
                'loan_id' => $loan->id,
                'organization_id' => $loan->organization_id,
                'installment_number' => $number,
                'due_date' => $disbursedAt->addMonthsNoOverflow($number)->toDateString(),
                'principal_due_cents' => $principalDue,
                'interest_due_cents' => $interestDue,
                'principal_paid_cents' => 0,
                'interest_paid_cents' => 0,
                'status' => LoanInstallment::STATUS_PENDING,
            ]);

            $allocatedPrincipal += $principalDue;
            $allocatedInterest += $interestDue;
        }
    }
}
