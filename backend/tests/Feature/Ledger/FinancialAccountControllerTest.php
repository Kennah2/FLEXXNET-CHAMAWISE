<?php

namespace Tests\Feature\Ledger;

use App\Models\FinancialAccount;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class FinancialAccountControllerTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_chairperson_can_create_a_financial_account_with_an_opening_balance(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Account Chama');

        $response = $this->actingAs($ken)->post(route('organizations.accounts.store', $organization), [
            'name' => 'Equity Bank',
            'type' => 'bank',
            'opening_balance' => '5000.00',
        ]);

        $account = FinancialAccount::firstWhere('name', 'Equity Bank');
        $response->assertRedirect(route('organizations.accounts.show', [$organization, $account]));
        $this->assertNotNull($account);
        $this->assertSame(500000, $account->opening_balance_cents);
        $this->assertTrue($account->is_default);
        $this->assertSame(500000, $account->balanceCents());
        $this->assertNotNull($account->ledgerAccount);
    }

    public function test_an_ordinary_member_cannot_create_a_financial_account(): void
    {
        [, $organization] = $this->createChamaWithChairperson('No Create Chama');
        $member = $this->addMemberWithRole($organization, 'member');

        $response = $this->actingAs($member)->post(route('organizations.accounts.store', $organization), [
            'name' => 'Should Not Exist',
            'type' => 'cash',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('financial_accounts', ['name' => 'Should Not Exist']);
    }

    public function test_only_the_first_account_becomes_default_unless_explicitly_chosen(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Default Account Chama');

        $this->actingAs($ken)->post(route('organizations.accounts.store', $organization), [
            'name' => 'Cash',
            'type' => 'cash',
        ]);
        $this->actingAs($ken)->post(route('organizations.accounts.store', $organization), [
            'name' => 'Bank',
            'type' => 'bank',
        ]);

        $this->assertTrue(FinancialAccount::firstWhere('name', 'Cash')->is_default);
        $this->assertFalse(FinancialAccount::firstWhere('name', 'Bank')->is_default);
    }

    public function test_setting_a_new_account_as_default_unsets_the_previous_default(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Switch Default Chama');

        $this->actingAs($ken)->post(route('organizations.accounts.store', $organization), ['name' => 'Cash', 'type' => 'cash']);
        $this->actingAs($ken)->post(route('organizations.accounts.store', $organization), [
            'name' => 'Bank', 'type' => 'bank', 'is_default' => '1',
        ]);

        $this->assertFalse(FinancialAccount::firstWhere('name', 'Cash')->fresh()->is_default);
        $this->assertTrue(FinancialAccount::firstWhere('name', 'Bank')->is_default);
    }

    public function test_recording_a_reconciliation_computes_the_variance(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Reconcile Chama');
        $this->actingAs($ken)->post(route('organizations.accounts.store', $organization), [
            'name' => 'Cash', 'type' => 'cash', 'opening_balance' => '1000.00',
        ]);
        $account = FinancialAccount::firstWhere('name', 'Cash');

        $response = $this->actingAs($ken)->post(route('organizations.accounts.reconcile', [$organization, $account]), [
            'statement_date' => now()->toDateString(),
            'statement_balance' => '950.00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('account_reconciliations', [
            'financial_account_id' => $account->id,
            'statement_balance_cents' => 95000,
            'computed_balance_cents' => 100000,
            'variance_cents' => -5000,
        ]);
    }

    public function test_a_member_of_another_chama_cannot_view_this_chamas_account(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Acc Chama A');
        [, $organizationB] = $this->createChamaWithChairperson('Acc Chama B');
        $memberOfB = $this->addMemberWithRole($organizationB, 'member');

        $this->actingAs($ken)->post(route('organizations.accounts.store', $organizationA), ['name' => 'Cash', 'type' => 'cash']);
        $account = FinancialAccount::firstWhere('name', 'Cash');

        $response = $this->actingAs($memberOfB)->get(route('organizations.accounts.show', [$organizationA, $account]));

        $response->assertForbidden();
    }
}
