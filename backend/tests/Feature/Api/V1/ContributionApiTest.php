<?php

namespace Tests\Feature\Api\V1;

use App\Models\Contribution;
use App\Models\FinancialAccount;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class ContributionApiTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_a_member_can_record_a_contribution_via_the_api(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Contribution Chama');

        Sanctum::actingAs($ken);

        $response = $this->postJson("/api/v1/chamas/{$organization->id}/contributions", [
            'amount' => '150.00',
            'payment_method' => 'cash',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', Contribution::STATUS_PENDING_VERIFICATION);
        $this->assertDatabaseHas('contributions', ['organization_id' => $organization->id, 'user_id' => $ken->id, 'amount_cents' => 15000]);
    }

    public function test_an_official_can_verify_a_contribution_via_the_api_and_it_posts_the_ledger(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Verify Chama');

        $provisioner = app(LedgerProvisioner::class);
        $provisioner->provisionSystemAccounts($organization);
        $account = FinancialAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Cash',
            'type' => FinancialAccount::TYPE_CASH,
            'currency' => $organization->currency,
            'opening_balance_cents' => 0,
            'is_default' => true,
            'created_by' => $ken->id,
        ]);
        $provisioner->provisionForFinancialAccount($account);

        Sanctum::actingAs($ken);

        $this->postJson("/api/v1/chamas/{$organization->id}/contributions", [
            'amount' => '200.00',
            'payment_method' => 'cash',
            'paid_at' => now()->toDateTimeString(),
        ])->assertCreated();

        $contribution = Contribution::firstWhere('organization_id', $organization->id);

        $verify = $this->postJson("/api/v1/chamas/{$organization->id}/contributions/{$contribution->id}/verify", [
            'financial_account_id' => $account->id,
        ]);

        $verify->assertOk();
        $verify->assertJsonPath('data.status', Contribution::STATUS_VERIFIED);
        $this->assertSame(20000, $account->fresh()->balanceCents());
    }

    public function test_a_member_cannot_verify_their_own_contribution_via_the_api(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api No Self Verify Chama');
        $frank = $this->addMemberWithRole($organization, 'member');

        Sanctum::actingAs($frank);
        $this->postJson("/api/v1/chamas/{$organization->id}/contributions", [
            'amount' => '50.00',
            'payment_method' => 'cash',
            'paid_at' => now()->toDateTimeString(),
        ])->assertCreated();

        $contribution = Contribution::firstWhere('user_id', $frank->id);

        $this->postJson("/api/v1/chamas/{$organization->id}/contributions/{$contribution->id}/verify", [
            'financial_account_id' => 1,
        ])->assertForbidden();
    }

    public function test_a_member_of_another_chama_cannot_see_or_act_on_this_chamas_contributions(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Api Contrib Chama A');
        [$outsider] = $this->createChamaWithChairperson('Api Contrib Chama B');

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/chamas/{$organizationA->id}/contributions")->assertForbidden();
        $this->postJson("/api/v1/chamas/{$organizationA->id}/contributions", [
            'amount' => '10.00',
            'payment_method' => 'cash',
            'paid_at' => now()->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_initiating_an_stk_push_via_the_api_without_daraja_configured_returns_a_friendly_error(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Mpesa Chama');

        Sanctum::actingAs($ken);

        $response = $this->postJson("/api/v1/chamas/{$organization->id}/contributions/mpesa", [
            'phone' => '0712345678',
            'amount' => '100',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
    }
}
