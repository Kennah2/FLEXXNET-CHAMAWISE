<?php

namespace Tests\Feature\Api\V1;

use App\Models\Contribution;
use App\Models\FinancialAccount;
use App\Models\WithdrawalRequest;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class WithdrawalApiTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Gives $member savings by recording and verifying a contribution for them (verified by
     * $official, since an ordinary member cannot verify their own payment — ContributionPolicy
     * requires manage-finances), exactly as a real withdrawal would need to draw against a real
     * balance.
     */
    private function giveSavings($organization, $official, $member, int $amountCents): FinancialAccount
    {
        $provisioner = app(LedgerProvisioner::class);
        $provisioner->provisionSystemAccounts($organization);
        $account = FinancialAccount::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Cash'],
            ['type' => FinancialAccount::TYPE_CASH, 'currency' => $organization->currency, 'opening_balance_cents' => 0, 'is_default' => true, 'created_by' => $official->id],
        );
        $provisioner->provisionForFinancialAccount($account);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/chamas/{$organization->id}/contributions", [
            'amount' => (string) ($amountCents / 100),
            'payment_method' => 'cash',
            'paid_at' => now()->toDateTimeString(),
        ]);
        $contribution = Contribution::where('organization_id', $organization->id)->where('user_id', $member->id)->latest('id')->first();

        Sanctum::actingAs($official);
        $this->postJson("/api/v1/chamas/{$organization->id}/contributions/{$contribution->id}/verify", [
            'financial_account_id' => $account->id,
        ])->assertOk();

        return $account;
    }

    public function test_a_member_can_request_and_an_official_can_approve_and_pay_a_withdrawal_via_the_api(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Withdrawal Chama');
        $treasurer = $this->addMemberWithRole($organization, 'treasurer');
        $member = $this->addMemberWithRole($organization, 'member');

        $account = $this->giveSavings($organization, $ken, $member, 100_00);

        Sanctum::actingAs($member);
        $request = $this->postJson("/api/v1/chamas/{$organization->id}/withdrawals", [
            'financial_account_id' => $account->id,
            'amount' => '40.00',
        ]);
        $request->assertCreated();
        $withdrawal = WithdrawalRequest::firstWhere('user_id', $member->id);

        // Member cannot approve their own.
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/chamas/{$organization->id}/withdrawals/{$withdrawal->id}/approve")->assertForbidden();

        Sanctum::actingAs($ken);
        $this->postJson("/api/v1/chamas/{$organization->id}/withdrawals/{$withdrawal->id}/approve")->assertOk();

        Sanctum::actingAs($treasurer);
        $approve2 = $this->postJson("/api/v1/chamas/{$organization->id}/withdrawals/{$withdrawal->id}/approve");
        $approve2->assertOk();
        $approve2->assertJsonPath('data.status', WithdrawalRequest::STATUS_APPROVED);

        $pay = $this->postJson("/api/v1/chamas/{$organization->id}/withdrawals/{$withdrawal->id}/pay");
        $pay->assertOk();
        $pay->assertJsonPath('data.status', WithdrawalRequest::STATUS_PAID);

        $this->assertSame(60_00, $account->fresh()->balanceCents());
    }

    public function test_requesting_more_than_the_balance_is_rejected_via_the_api(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Over Withdrawal Chama');
        $member = $this->addMemberWithRole($organization, 'member');
        $account = $this->giveSavings($organization, $ken, $member, 10_00);

        Sanctum::actingAs($member);
        $response = $this->postJson("/api/v1/chamas/{$organization->id}/withdrawals", [
            'financial_account_id' => $account->id,
            'amount' => '50.00',
        ]);

        $response->assertStatus(422);
    }
}
