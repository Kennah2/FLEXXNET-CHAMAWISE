<?php

namespace Tests\Feature\Api\V1;

use App\Models\FinancialAccount;
use App\Models\Loan;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class LoanApiTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function cashAccount($organization, $creator): FinancialAccount
    {
        $provisioner = app(LedgerProvisioner::class);
        $provisioner->provisionSystemAccounts($organization);

        $account = FinancialAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Cash',
            'type' => FinancialAccount::TYPE_CASH,
            'currency' => $organization->currency,
            'opening_balance_cents' => 10_000_000,
            'is_default' => true,
            'created_by' => $creator->id,
        ]);
        $provisioner->provisionForFinancialAccount($account);

        return $account;
    }

    public function test_a_member_can_apply_approve_disburse_and_repay_a_loan_via_the_api(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Loan Chama');
        $frank = $this->addMemberWithRole($organization, 'member');
        $account = $this->cashAccount($organization, $ken);

        Sanctum::actingAs($frank);
        $apply = $this->postJson("/api/v1/chamas/{$organization->id}/loans", [
            'amount' => '60000.00',
            'term_months' => 12,
        ]);
        $apply->assertCreated();
        $apply->assertJsonPath('data.status', Loan::STATUS_PENDING);

        $loan = Loan::firstWhere('user_id', $frank->id);

        Sanctum::actingAs($ken);
        $approve = $this->postJson("/api/v1/chamas/{$organization->id}/loans/{$loan->id}/approve", [
            'approved_amount' => '60000.00',
            'term_months' => 12,
            'interest_rate' => '10.00',
        ]);
        $approve->assertOk();
        $approve->assertJsonPath('data.status', Loan::STATUS_APPROVED);

        $disburse = $this->postJson("/api/v1/chamas/{$organization->id}/loans/{$loan->id}/disburse", [
            'financial_account_id' => $account->id,
        ]);
        $disburse->assertOk();
        $disburse->assertJsonPath('data.status', Loan::STATUS_DISBURSED);
        $this->assertSame(12, $loan->fresh()->installments()->count());

        Sanctum::actingAs($frank);
        // 60,000.00 principal / 12 months = 500,000 cents/installment; 10% flat annual interest
        // = 600,000 cents total / 12 = 50,000 cents/installment. One installment's worth, exactly.
        $repay = $this->postJson("/api/v1/chamas/{$organization->id}/loans/{$loan->id}/repayments", [
            'financial_account_id' => $account->id,
            'amount' => '5500.00',
        ]);
        $repay->assertCreated();
        $repay->assertJsonPath('data.principal_cents', 500_000);
        $repay->assertJsonPath('data.interest_cents', 50_000);
    }

    public function test_a_borrower_cannot_approve_their_own_loan_via_the_api(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api No Self Approve Loan Chama');

        Sanctum::actingAs($ken);
        $this->postJson("/api/v1/chamas/{$organization->id}/loans", [
            'amount' => '10000.00',
            'term_months' => 6,
        ])->assertCreated();

        $loan = Loan::firstWhere('user_id', $ken->id);

        $this->postJson("/api/v1/chamas/{$organization->id}/loans/{$loan->id}/approve", [
            'approved_amount' => '10000.00',
            'term_months' => 6,
            'interest_rate' => '10',
        ])->assertForbidden();
    }

    public function test_a_member_of_another_chama_cannot_see_or_act_on_this_chamas_loans(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Api Loan Chama A');
        [$outsider] = $this->createChamaWithChairperson('Api Loan Chama B');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/chamas/{$organizationA->id}/loans")->assertForbidden();
        $this->postJson("/api/v1/chamas/{$organizationA->id}/loans", [
            'amount' => '10.00',
            'term_months' => 1,
        ])->assertForbidden();
    }

    public function test_the_index_separates_pending_applications_and_ready_to_disburse_for_officials_only(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Loan Index Chama');
        $frank = $this->addMemberWithRole($organization, 'member');

        Sanctum::actingAs($frank);
        $this->postJson("/api/v1/chamas/{$organization->id}/loans", [
            'amount' => '10000.00',
            'term_months' => 6,
        ])->assertCreated();

        $asMember = $this->getJson("/api/v1/chamas/{$organization->id}/loans");
        $asMember->assertJsonPath('data.can_manage_finances', false);
        $asMember->assertJsonCount(0, 'data.pending_applications');

        Sanctum::actingAs($ken);
        $asOfficial = $this->getJson("/api/v1/chamas/{$organization->id}/loans");
        $asOfficial->assertJsonPath('data.can_manage_finances', true);
        $asOfficial->assertJsonCount(1, 'data.pending_applications');
    }
}
