<?php

namespace Tests\Feature\Ledger;

use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class WithdrawalControllerTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Gives $user savings of $cents by posting a ledger entry directly (bypassing the
     * contribution-verification flow, which these tests don't need).
     */
    private function giveSavings(Organization $organization, User $user, int $cents, FinancialAccount $account): void
    {
        app(LedgerPoster::class)->post(
            $organization,
            'Test deposit',
            [
                ['ledger_account_id' => $account->ledgerAccount->id, 'debit_cents' => $cents],
                ['ledger_account_id' => $organization->memberSavingsAccount()->id, 'credit_cents' => $cents, 'user_id' => $user->id],
            ],
            $user,
        );
    }

    private function cashAccount(Organization $organization, User $creator): FinancialAccount
    {
        $provisioner = app(LedgerProvisioner::class);
        $provisioner->provisionSystemAccounts($organization);

        $account = FinancialAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Cash',
            'type' => FinancialAccount::TYPE_CASH,
            'currency' => $organization->currency,
            'opening_balance_cents' => 0,
            'is_default' => true,
            'created_by' => $creator->id,
        ]);
        $provisioner->provisionForFinancialAccount($account);

        return $account;
    }

    public function test_a_member_can_request_a_withdrawal_within_their_balance(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Withdraw Chama');
        $account = $this->cashAccount($organization, $ken);
        $this->giveSavings($organization, $ken, 10000, $account);

        $response = $this->actingAs($ken)->post(route('organizations.withdrawals.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '50.00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('withdrawal_requests', [
            'organization_id' => $organization->id,
            'user_id' => $ken->id,
            'amount_cents' => 5000,
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);
    }

    public function test_requesting_more_than_the_savings_balance_is_rejected(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Overdraw Chama');
        $account = $this->cashAccount($organization, $ken);
        $this->giveSavings($organization, $ken, 5000, $account);

        $response = $this->actingAs($ken)->post(route('organizations.withdrawals.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '50.01',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('withdrawal_requests', ['organization_id' => $organization->id]);
    }

    public function test_the_requester_cannot_approve_their_own_withdrawal(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Self Approve Chama');
        $account = $this->cashAccount($organization, $ken);
        $this->giveSavings($organization, $ken, 10000, $account);
        $this->actingAs($ken)->post(route('organizations.withdrawals.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '50.00',
        ]);
        $withdrawal = WithdrawalRequest::firstWhere('user_id', $ken->id);

        $response = $this->actingAs($ken)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]));

        $response->assertForbidden();
        $this->assertSame(WithdrawalRequest::STATUS_PENDING, $withdrawal->fresh()->status);
    }

    public function test_a_withdrawal_needs_the_required_number_of_distinct_approvals_before_it_is_approved(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Two Approvals Chama');
        $account = $this->cashAccount($organization, $ken);
        $treasurer = $this->addMemberWithRole($organization, 'treasurer');
        $secretary = $this->addMemberWithRole($organization, 'secretary');
        $this->giveSavings($organization, $ken, 10000, $account);

        $this->actingAs($ken)->post(route('organizations.withdrawals.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '50.00',
        ]);
        $withdrawal = WithdrawalRequest::firstWhere('user_id', $ken->id);
        $this->assertSame(2, $withdrawal->required_approvals);

        $this->actingAs($treasurer)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]));
        $this->assertSame(WithdrawalRequest::STATUS_PENDING, $withdrawal->fresh()->status);

        // secretary has no manage-finances permission by default — cannot approve.
        $this->actingAs($secretary)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]))
            ->assertForbidden();

        // A second treasurer-equivalent approval (another manage-finances holder) completes it.
        $anotherOfficial = $this->addMemberWithRole($organization, 'treasurer');
        $this->actingAs($anotherOfficial)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]));

        $this->assertSame(WithdrawalRequest::STATUS_APPROVED, $withdrawal->fresh()->status);
    }

    public function test_a_single_rejection_rejects_the_whole_request(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Reject Veto Chama');
        $account = $this->cashAccount($organization, $ken);
        $treasurer = $this->addMemberWithRole($organization, 'treasurer');
        $this->giveSavings($organization, $ken, 10000, $account);

        $this->actingAs($ken)->post(route('organizations.withdrawals.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '50.00',
        ]);
        $withdrawal = WithdrawalRequest::firstWhere('user_id', $ken->id);

        $this->actingAs($treasurer)->post(route('organizations.withdrawals.reject', [$organization, $withdrawal]), [
            'reason' => 'Not this time',
        ]);

        $this->assertSame(WithdrawalRequest::STATUS_REJECTED, $withdrawal->fresh()->status);
    }

    public function test_paying_an_approved_withdrawal_posts_the_ledger_and_updates_balances(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Pay Chama');
        $account = $this->cashAccount($organization, $ken);
        $treasurer = $this->addMemberWithRole($organization, 'treasurer');
        $official2 = $this->addMemberWithRole($organization, 'treasurer');
        $this->giveSavings($organization, $ken, 10000, $account);

        $this->actingAs($ken)->post(route('organizations.withdrawals.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '40.00',
        ]);
        $withdrawal = WithdrawalRequest::firstWhere('user_id', $ken->id);
        $this->actingAs($treasurer)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]));
        $this->actingAs($official2)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]));
        $this->assertSame(WithdrawalRequest::STATUS_APPROVED, $withdrawal->fresh()->status);

        $response = $this->actingAs($treasurer)->post(route('organizations.withdrawals.pay', [$organization, $withdrawal]));
        $response->assertRedirect();

        $withdrawal->refresh();
        $this->assertSame(WithdrawalRequest::STATUS_PAID, $withdrawal->status);
        $this->assertNotNull($withdrawal->journal_entry_id);
        $this->assertSame(6000, $account->fresh()->balanceCents());
        $this->assertSame(6000, $organization->memberSavingsAccount()->balanceCents($ken->id));
    }

    public function test_paying_is_refused_if_the_members_balance_has_since_dropped_below_the_request(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Balance Dropped Chama');
        $account = $this->cashAccount($organization, $ken);
        $treasurer = $this->addMemberWithRole($organization, 'treasurer');
        $official2 = $this->addMemberWithRole($organization, 'treasurer');
        $this->giveSavings($organization, $ken, 10000, $account);

        $this->actingAs($ken)->post(route('organizations.withdrawals.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '80.00',
        ]);
        $withdrawal = WithdrawalRequest::firstWhere('user_id', $ken->id);
        $this->actingAs($treasurer)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]));
        $this->actingAs($official2)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]));

        // Ken's balance drops to 50 via a second, directly-posted withdrawal before this one is paid.
        app(LedgerPoster::class)->post(
            $organization,
            'Competing withdrawal already paid',
            [
                ['ledger_account_id' => $organization->memberSavingsAccount()->id, 'debit_cents' => 5000, 'user_id' => $ken->id],
                ['ledger_account_id' => $account->ledgerAccount->id, 'credit_cents' => 5000],
            ],
            $treasurer,
        );

        $response = $this->actingAs($treasurer)->post(route('organizations.withdrawals.pay', [$organization, $withdrawal]));

        $response->assertStatus(422);
        $this->assertSame(WithdrawalRequest::STATUS_APPROVED, $withdrawal->fresh()->status);
    }

    public function test_requester_can_cancel_a_pending_request(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Cancel Chama');
        $account = $this->cashAccount($organization, $ken);
        $this->giveSavings($organization, $ken, 10000, $account);

        $this->actingAs($ken)->post(route('organizations.withdrawals.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '20.00',
        ]);
        $withdrawal = WithdrawalRequest::firstWhere('user_id', $ken->id);

        $response = $this->actingAs($ken)->post(route('organizations.withdrawals.cancel', [$organization, $withdrawal]));

        $response->assertRedirect();
        $this->assertSame(WithdrawalRequest::STATUS_CANCELLED, $withdrawal->fresh()->status);
    }

    public function test_reversing_a_paid_withdrawal_restores_the_members_balance(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Reverse Withdrawal Chama');
        $account = $this->cashAccount($organization, $ken);
        $treasurer = $this->addMemberWithRole($organization, 'treasurer');
        $official2 = $this->addMemberWithRole($organization, 'treasurer');
        $this->giveSavings($organization, $ken, 10000, $account);

        $this->actingAs($ken)->post(route('organizations.withdrawals.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '40.00',
        ]);
        $withdrawal = WithdrawalRequest::firstWhere('user_id', $ken->id);
        $this->actingAs($treasurer)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]));
        $this->actingAs($official2)->post(route('organizations.withdrawals.approve', [$organization, $withdrawal]));
        $this->actingAs($treasurer)->post(route('organizations.withdrawals.pay', [$organization, $withdrawal]));

        $response = $this->actingAs($treasurer)->post(route('organizations.withdrawals.reverse', [$organization, $withdrawal]));

        $response->assertRedirect();
        $this->assertSame(WithdrawalRequest::STATUS_REVERSED, $withdrawal->fresh()->status);
        $this->assertSame(10000, $account->fresh()->balanceCents());
        $this->assertSame(10000, $organization->memberSavingsAccount()->balanceCents($ken->id));
    }

    public function test_a_member_of_another_chama_cannot_view_this_chamas_withdrawals(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Withdraw Chama A');
        [, $organizationB] = $this->createChamaWithChairperson('Withdraw Chama B');
        $memberOfB = $this->addMemberWithRole($organizationB, 'member');

        $response = $this->actingAs($memberOfB)->get(route('organizations.withdrawals.index', $organizationA));

        $response->assertForbidden();
    }
}
