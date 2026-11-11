<?php

namespace Tests\Feature\Api\V1;

use App\Models\Expense;
use App\Models\FinancialAccount;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class ExpenseAccountApiTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_an_official_can_create_a_financial_account_via_the_api(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Account Chama');

        Sanctum::actingAs($ken);
        $response = $this->postJson("/api/v1/chamas/{$organization->id}/accounts", [
            'name' => 'Equity Bank',
            'type' => 'bank',
            'opening_balance' => '500.00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Equity Bank');
        $response->assertJsonPath('data.balance_cents', 50000);

        $account = FinancialAccount::firstWhere('name', 'Equity Bank');
        $this->assertTrue($account->is_default);
    }

    public function test_an_ordinary_member_cannot_create_a_financial_account_via_the_api(): void
    {
        [, $organization] = $this->createChamaWithChairperson('Api No Account Chama');
        $member = $this->addMemberWithRole($organization, 'member');

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/chamas/{$organization->id}/accounts", [
            'name' => 'Should Not Exist',
            'type' => 'cash',
        ])->assertForbidden();
    }

    public function test_reconciling_an_account_via_the_api_computes_the_variance(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Reconcile Chama');

        Sanctum::actingAs($ken);
        $this->postJson("/api/v1/chamas/{$organization->id}/accounts", [
            'name' => 'Cash', 'type' => 'cash', 'opening_balance' => '1000.00',
        ]);
        $account = FinancialAccount::firstWhere('name', 'Cash');

        $response = $this->postJson("/api/v1/chamas/{$organization->id}/accounts/{$account->id}/reconcile", [
            'statement_date' => now()->toDateString(),
            'statement_balance' => '950.00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.variance_cents', -5000);
        $response->assertJsonPath('data.is_balanced', false);
    }

    public function test_an_official_can_record_and_reverse_an_expense_via_the_api(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Expense Chama');

        Sanctum::actingAs($ken);
        $this->postJson("/api/v1/chamas/{$organization->id}/accounts", [
            'name' => 'Cash', 'type' => 'cash', 'opening_balance' => '1000.00',
        ]);
        $account = FinancialAccount::firstWhere('name', 'Cash');

        $record = $this->postJson("/api/v1/chamas/{$organization->id}/expenses", [
            'financial_account_id' => $account->id,
            'amount' => '150.00',
            'description' => 'Stationery',
            'expense_date' => now()->toDateString(),
        ]);
        $record->assertCreated();
        $this->assertSame(85000, $account->fresh()->balanceCents());

        $expense = Expense::firstWhere('description', 'Stationery');
        $reverse = $this->postJson("/api/v1/chamas/{$organization->id}/expenses/{$expense->id}/reverse");
        $reverse->assertOk();
        $reverse->assertJsonPath('data.status', Expense::STATUS_REVERSED);
        $this->assertSame(100000, $account->fresh()->balanceCents());
    }

    public function test_accounting_and_transactions_endpoints_reflect_posted_activity(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Accounting Chama');

        Sanctum::actingAs($ken);
        $this->postJson("/api/v1/chamas/{$organization->id}/accounts", [
            'name' => 'Cash', 'type' => 'cash', 'opening_balance' => '0',
        ]);
        $account = FinancialAccount::firstWhere('name', 'Cash');
        $this->postJson("/api/v1/chamas/{$organization->id}/expenses", [
            'financial_account_id' => $account->id,
            'amount' => '20.00',
            'description' => 'Tea',
            'expense_date' => now()->toDateString(),
        ])->assertCreated();

        $accounting = $this->getJson("/api/v1/chamas/{$organization->id}/accounting");
        $accounting->assertOk();
        $accounting->assertJsonPath('data.is_balanced', true);

        $transactions = $this->getJson("/api/v1/chamas/{$organization->id}/transactions");
        $transactions->assertOk();
        $descriptions = collect($transactions->json('data'))->pluck('description');
        $this->assertTrue($descriptions->contains('Expense: Tea'));
    }

    public function test_a_member_of_another_chama_cannot_view_this_chamas_accounts_or_expenses(): void
    {
        [, $organizationA] = $this->createChamaWithChairperson('Api Accounts Chama A');
        [$outsider] = $this->createChamaWithChairperson('Api Accounts Chama B');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/chamas/{$organizationA->id}/accounts")->assertForbidden();
        $this->getJson("/api/v1/chamas/{$organizationA->id}/expenses")->assertForbidden();
    }
}
