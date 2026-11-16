<?php

namespace Tests\Feature\Loans;

use App\Models\FinancialAccount;
use App\Models\LedgerAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Organization;
use App\Models\User;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class LoanControllerTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function cashAccount(Organization $organization, User $creator, int $openingBalanceCents = 1_000_000): FinancialAccount
    {
        $provisioner = app(LedgerProvisioner::class);
        $provisioner->provisionSystemAccounts($organization);

        $account = FinancialAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Cash',
            'type' => FinancialAccount::TYPE_CASH,
            'currency' => $organization->currency,
            'opening_balance_cents' => $openingBalanceCents,
            'is_default' => true,
            'created_by' => $creator->id,
        ]);
        $provisioner->provisionForFinancialAccount($account);

        return $account;
    }

    /**
     * Applies, approves (12 months, 12% flat), and disburses a loan for $member, approved by
     * $official. Returns the fresh Loan and the disbursing FinancialAccount.
     */
    private function disbursedLoan(Organization $organization, User $official, User $member, int $amountCents, FinancialAccount $account): Loan
    {
        $this->actingAs($member)->post(route('organizations.loans.store', $organization), [
            'amount' => (string) ($amountCents / 100),
            'term_months' => 12,
        ]);
        $loan = Loan::where('organization_id', $organization->id)->where('user_id', $member->id)->latest('id')->first();

        $this->actingAs($official)->post(route('organizations.loans.approve', [$organization, $loan]), [
            'approved_amount' => (string) ($amountCents / 100),
            'term_months' => 12,
            'interest_rate' => '12.00',
        ]);

        $this->actingAs($official)->post(route('organizations.loans.disburse', [$organization, $loan]), [
            'financial_account_id' => $account->id,
        ]);

        return $loan->fresh();
    }

    public function test_a_member_can_apply_for_a_loan(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan Apply Chama');

        $response = $this->actingAs($ken)->post(route('organizations.loans.store', $organization), [
            'amount' => '50000.00',
            'term_months' => 12,
            'reason' => 'School fees',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loans', [
            'organization_id' => $organization->id,
            'user_id' => $ken->id,
            'requested_amount_cents' => 5_000_000,
            'status' => Loan::STATUS_PENDING,
        ]);
    }

    public function test_an_official_can_approve_setting_the_actual_terms(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan Approve Chama');
        $frank = $this->addMemberWithRole($organization, 'member');

        $this->actingAs($frank)->post(route('organizations.loans.store', $organization), [
            'amount' => '50000.00',
            'term_months' => 10,
        ]);
        $loan = Loan::firstWhere('user_id', $frank->id);

        $response = $this->actingAs($ken)->post(route('organizations.loans.approve', [$organization, $loan]), [
            'approved_amount' => '45000.00', // committee reduced the requested amount
            'term_months' => 12,
            'interest_rate' => '12.5',
        ]);

        $response->assertRedirect();
        $loan->refresh();
        $this->assertSame(Loan::STATUS_APPROVED, $loan->status);
        $this->assertSame(4_500_000, $loan->approved_amount_cents);
        $this->assertSame(12, $loan->term_months);
        $this->assertSame(1250, $loan->interest_rate_bps);
    }

    public function test_a_borrower_cannot_approve_their_own_loan(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan No Self Approve Chama');

        $this->actingAs($ken)->post(route('organizations.loans.store', $organization), [
            'amount' => '20000.00',
            'term_months' => 6,
        ]);
        $loan = Loan::firstWhere('user_id', $ken->id);

        $response = $this->actingAs($ken)->post(route('organizations.loans.approve', [$organization, $loan]), [
            'approved_amount' => '20000.00',
            'term_months' => 6,
            'interest_rate' => '10',
        ]);

        $response->assertForbidden();
        $this->assertSame(Loan::STATUS_PENDING, $loan->fresh()->status);
    }

    public function test_an_official_can_reject_a_pending_application(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan Reject Chama');
        $frank = $this->addMemberWithRole($organization, 'member');

        $this->actingAs($frank)->post(route('organizations.loans.store', $organization), [
            'amount' => '10000.00',
            'term_months' => 6,
        ]);
        $loan = Loan::firstWhere('user_id', $frank->id);

        $this->actingAs($ken)->post(route('organizations.loans.reject', [$organization, $loan]));

        $this->assertSame(Loan::STATUS_REJECTED, $loan->fresh()->status);
    }

    public function test_the_requester_can_cancel_a_pending_application(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan Cancel Chama');

        $this->actingAs($ken)->post(route('organizations.loans.store', $organization), [
            'amount' => '10000.00',
            'term_months' => 6,
        ]);
        $loan = Loan::firstWhere('user_id', $ken->id);

        $this->actingAs($ken)->post(route('organizations.loans.cancel', [$organization, $loan]));

        $this->assertSame(Loan::STATUS_CANCELLED, $loan->fresh()->status);
    }

    public function test_disbursing_posts_the_ledger_and_generates_the_installment_schedule(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan Disburse Chama');
        $frank = $this->addMemberWithRole($organization, 'member');
        $account = $this->cashAccount($organization, $ken);

        $loan = $this->disbursedLoan($organization, $ken, $frank, 120_000, $account);

        $this->assertSame(Loan::STATUS_DISBURSED, $loan->status);
        $this->assertSame(880_000, $account->fresh()->balanceCents()); // 1,000,000 opening - 120,000 disbursed

        $loansReceivable = $organization->loansReceivableAccount();
        $this->assertSame(120_000, $loansReceivable->balanceCents($frank->id));

        $this->assertSame(12, $loan->installments()->count());
        $this->assertSame(120_000, $loan->installments()->sum('principal_due_cents'));
    }

    public function test_a_borrower_cannot_disburse_their_own_loan(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan No Self Disburse Chama');
        $treasurer = $this->addMemberWithRole($organization, 'treasurer');
        $account = $this->cashAccount($organization, $ken);

        $this->actingAs($ken)->post(route('organizations.loans.store', $organization), [
            'amount' => '10000.00',
            'term_months' => 6,
        ]);
        $loan = Loan::firstWhere('user_id', $ken->id);
        // A different official approves — this test is about the self-*disburse* block, not
        // self-approval (already covered above), so the approval itself must succeed first.
        $this->actingAs($treasurer)->post(route('organizations.loans.approve', [$organization, $loan]), [
            'approved_amount' => '10000.00', 'term_months' => 6, 'interest_rate' => '10',
        ]);
        $this->assertSame(Loan::STATUS_APPROVED, $loan->fresh()->status);

        $response = $this->actingAs($ken)->post(route('organizations.loans.disburse', [$organization, $loan]), [
            'financial_account_id' => $account->id,
        ]);

        $response->assertForbidden();
        $this->assertSame(Loan::STATUS_APPROVED, $loan->fresh()->status);
    }

    public function test_a_repayment_is_allocated_interest_first_and_posts_the_ledger(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan Repay Chama');
        $frank = $this->addMemberWithRole($organization, 'member');
        $account = $this->cashAccount($organization, $ken);

        // 120,000 principal, 12 months, 12% flat => 10,000 principal + 1,200 interest / installment.
        $loan = $this->disbursedLoan($organization, $ken, $frank, 120_000, $account);

        $response = $this->actingAs($frank)->post(route('organizations.loans.repayments.store', [$organization, $loan]), [
            'financial_account_id' => $account->id,
            'amount' => '112.00', // exactly covers installment 1's principal+interest (10000+1200=11200 cents)
        ]);

        $response->assertRedirect();

        $repayment = LoanRepayment::firstWhere('loan_id', $loan->id);
        $this->assertSame(10_000, $repayment->principal_cents);
        $this->assertSame(1_200, $repayment->interest_cents);

        $firstInstallment = $loan->installments()->orderBy('installment_number')->first();
        $this->assertSame(10_000, $firstInstallment->principal_paid_cents);
        $this->assertSame(1_200, $firstInstallment->interest_paid_cents);
        $this->assertSame('paid', $firstInstallment->status);

        // Ledger: cash up by 112.00, loans_receivable down by 100.00 (principal), interest income up by 12.00.
        $this->assertSame(880_000 + 11_200, $account->fresh()->balanceCents());
        $this->assertSame(120_000 - 10_000, $organization->loansReceivableAccount()->balanceCents($frank->id));
        $interestIncome = $organization->ledgerAccounts()->where('code', LedgerAccount::CODE_INTEREST_INCOME)->first();
        $this->assertSame(1_200, $interestIncome->balanceCents());
    }

    public function test_repaying_more_than_the_outstanding_balance_is_rejected(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan Overpay Chama');
        $frank = $this->addMemberWithRole($organization, 'member');
        $account = $this->cashAccount($organization, $ken);

        $loan = $this->disbursedLoan($organization, $ken, $frank, 10_000, $account); // tiny loan, 1 year term

        $response = $this->actingAs($frank)->post(route('organizations.loans.repayments.store', [$organization, $loan]), [
            'financial_account_id' => $account->id,
            'amount' => '999999.00',
        ]);

        $response->assertStatus(422);
    }

    public function test_fully_repaying_a_loan_marks_it_completed_and_reversing_the_final_repayment_reopens_it(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Loan Complete Chama');
        $frank = $this->addMemberWithRole($organization, 'member');
        $account = $this->cashAccount($organization, $ken);

        $loan = $this->disbursedLoan($organization, $ken, $frank, 12_000, $account); // 12,000 cents / 12mo = 1,000/mo + 10bps*12000*12/1200=... use round rate
        // Approve used 12% flat over 12 months => total interest = 12000*0.12 = 1440, /12 = 120/installment.
        $totalDue = $loan->installments()->sum('principal_due_cents') + $loan->installments()->sum('interest_due_cents');

        $this->actingAs($frank)->post(route('organizations.loans.repayments.store', [$organization, $loan]), [
            'financial_account_id' => $account->id,
            'amount' => (string) ($totalDue / 100),
        ]);

        $loan->refresh();
        $this->assertSame(Loan::STATUS_COMPLETED, $loan->status);

        $repayment = LoanRepayment::firstWhere('loan_id', $loan->id);
        $this->actingAs($ken)->post(route('organizations.loans.repayments.reverse', [$organization, $loan, $repayment]));

        $loan->refresh();
        $this->assertSame(Loan::STATUS_DISBURSED, $loan->status);
        $this->assertSame(LoanRepayment::STATUS_REVERSED, $repayment->fresh()->status);
        $this->assertSame(0, $loan->installments()->sum('principal_paid_cents'));
        $this->assertSame(12_000, $organization->loansReceivableAccount()->balanceCents($frank->id));
    }

    public function test_a_member_of_another_chama_cannot_view_or_act_on_this_chamas_loans(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Loan Chama A');
        $accountA = $this->cashAccount($organizationA, $ken);
        $frankA = $this->addMemberWithRole($organizationA, 'member');
        $loan = $this->disbursedLoan($organizationA, $ken, $frankA, 10_000, $accountA);

        [$outsider] = $this->createChamaWithChairperson('Loan Chama B');

        $this->actingAs($outsider)->get(route('organizations.loans.show', [$organizationA, $loan]))->assertForbidden();
        $this->actingAs($outsider)->post(route('organizations.loans.reject', [$organizationA, $loan]))->assertForbidden();
    }

    public function test_an_ordinary_member_cannot_approve_reject_or_disburse_another_members_loan(): void
    {
        [, $organization] = $this->createChamaWithChairperson('Loan Member Denied Chama');
        $account = $this->cashAccount($organization, User::factory()->create());
        $frank = $this->addMemberWithRole($organization, 'member');
        $grace = $this->addMemberWithRole($organization, 'member');

        $this->actingAs($frank)->post(route('organizations.loans.store', $organization), [
            'amount' => '10000.00',
            'term_months' => 6,
        ]);
        $loan = Loan::firstWhere('user_id', $frank->id);

        $this->actingAs($grace)->post(route('organizations.loans.approve', [$organization, $loan]), [
            'approved_amount' => '10000.00', 'term_months' => 6, 'interest_rate' => '10',
        ])->assertForbidden();

        $this->actingAs($grace)->post(route('organizations.loans.reject', [$organization, $loan]))->assertForbidden();

        $this->actingAs($grace)->post(route('organizations.loans.disburse', [$organization, $loan]), [
            'financial_account_id' => $account->id,
        ])->assertForbidden();
    }
}
