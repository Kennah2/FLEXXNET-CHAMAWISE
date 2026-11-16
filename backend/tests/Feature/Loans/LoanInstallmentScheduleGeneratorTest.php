<?php

namespace Tests\Feature\Loans;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Services\Loans\LoanInstallmentScheduleGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class LoanInstallmentScheduleGeneratorTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function approvedLoan(int $amountCents, int $termMonths, int $rateBps): Loan
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan Schedule Chama '.uniqid());

        return Loan::create([
            'organization_id' => $organization->id,
            'user_id' => $ken->id,
            'requested_by' => $ken->id,
            'requested_amount_cents' => $amountCents,
            'requested_term_months' => $termMonths,
            'status' => Loan::STATUS_APPROVED,
            'approved_amount_cents' => $amountCents,
            'term_months' => $termMonths,
            'interest_rate_bps' => $rateBps,
            'approved_by' => $ken->id,
            'approved_at' => now(),
        ]);
    }

    public function test_an_evenly_divisible_principal_and_interest_split_exactly_across_installments(): void
    {
        $loan = $this->approvedLoan(amountCents: 120_000, termMonths: 12, rateBps: 1200); // 12% flat p.a.

        (new LoanInstallmentScheduleGenerator)->generate($loan, CarbonImmutable::parse('2026-01-01'));

        $installments = LoanInstallment::where('loan_id', $loan->id)->orderBy('installment_number')->get();

        $this->assertCount(12, $installments);
        // Total interest = 120000 * 0.12 * (12/12) = 14400, /12 = 1200 exactly per installment.
        foreach ($installments as $installment) {
            $this->assertSame(10_000, $installment->principal_due_cents);
            $this->assertSame(1_200, $installment->interest_due_cents);
        }
        $this->assertSame(120_000, $installments->sum('principal_due_cents'));
        $this->assertSame(14_400, $installments->sum('interest_due_cents'));
    }

    public function test_a_remainder_from_integer_division_is_absorbed_entirely_by_the_last_installment(): void
    {
        // 100000 cents over 3 months does not divide evenly (33333.33...).
        $loan = $this->approvedLoan(amountCents: 100_000, termMonths: 3, rateBps: 1000);

        (new LoanInstallmentScheduleGenerator)->generate($loan, CarbonImmutable::parse('2026-01-01'));

        $installments = LoanInstallment::where('loan_id', $loan->id)->orderBy('installment_number')->get();

        $this->assertSame(33_333, $installments[0]->principal_due_cents);
        $this->assertSame(33_333, $installments[1]->principal_due_cents);
        $this->assertSame(33_334, $installments[2]->principal_due_cents); // absorbs the remainder

        // Not a single cent gained or lost to rounding, in either direction.
        $this->assertSame(100_000, $installments->sum('principal_due_cents'));
    }

    public function test_a_zero_interest_rate_produces_zero_interest_due_with_no_division_by_zero(): void
    {
        $loan = $this->approvedLoan(amountCents: 60_000, termMonths: 6, rateBps: 0);

        (new LoanInstallmentScheduleGenerator)->generate($loan, CarbonImmutable::parse('2026-01-01'));

        $installments = LoanInstallment::where('loan_id', $loan->id)->get();

        $this->assertSame(0, $installments->sum('interest_due_cents'));
        $this->assertSame(60_000, $installments->sum('principal_due_cents'));
    }

    public function test_a_single_installment_term_puts_the_entire_principal_and_interest_in_it(): void
    {
        $loan = $this->approvedLoan(amountCents: 50_000, termMonths: 1, rateBps: 500);

        (new LoanInstallmentScheduleGenerator)->generate($loan, CarbonImmutable::parse('2026-01-01'));

        $installment = LoanInstallment::where('loan_id', $loan->id)->sole();

        $this->assertSame(50_000, $installment->principal_due_cents);
        $this->assertSame(intdiv(50_000 * 500, 10_000 * 12), $installment->interest_due_cents);
    }

    public function test_due_dates_advance_one_month_at_a_time_from_disbursement_without_overflowing_short_months(): void
    {
        // Disbursed 31 Jan — naive "+1 month" per installment would overflow into March for the
        // February installment; addMonthsNoOverflow must clamp to the 28th instead.
        $loan = $this->approvedLoan(amountCents: 30_000, termMonths: 3, rateBps: 600);

        (new LoanInstallmentScheduleGenerator)->generate($loan, CarbonImmutable::parse('2026-01-31'));

        $installments = LoanInstallment::where('loan_id', $loan->id)->orderBy('installment_number')->get();

        $this->assertSame('2026-02-28', $installments[0]->due_date->toDateString());
        $this->assertSame('2026-03-31', $installments[1]->due_date->toDateString());
        $this->assertSame('2026-04-30', $installments[2]->due_date->toDateString());
    }
}
