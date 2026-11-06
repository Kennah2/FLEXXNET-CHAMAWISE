<?php

namespace Tests\Feature\Ledger;

use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function cashAccount(Organization $organization, User $creator, int $openingBalanceCents = 100000): FinancialAccount
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

    public function test_recording_an_expense_posts_the_ledger_and_reduces_the_account_balance(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Expense Chama');
        $account = $this->cashAccount($organization, $ken);

        $response = $this->actingAs($ken)->post(route('organizations.expenses.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '150.00',
            'description' => 'Stationery',
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $expense = Expense::firstWhere('description', 'Stationery');
        $this->assertNotNull($expense);
        $this->assertNotNull($expense->journal_entry_id);
        $this->assertSame(85000, $account->fresh()->balanceCents());

        $generalExpense = $organization->ledgerAccounts()->where('code', 'general_expense')->first();
        $this->assertSame(15000, $generalExpense->balanceCents());
    }

    public function test_an_ordinary_member_cannot_record_an_expense(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('No Expense Chama');
        $account = $this->cashAccount($organization, $ken);
        $member = $this->addMemberWithRole($organization, 'member');

        $response = $this->actingAs($member)->post(route('organizations.expenses.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '150.00',
            'description' => 'Should not be allowed',
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('expenses', ['description' => 'Should not be allowed']);
    }

    public function test_reversing_an_expense_restores_the_account_balance(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Reverse Expense Chama');
        $account = $this->cashAccount($organization, $ken);

        $this->actingAs($ken)->post(route('organizations.expenses.store', $organization), [
            'financial_account_id' => $account->id,
            'amount' => '150.00',
            'description' => 'Stationery',
            'expense_date' => now()->toDateString(),
        ]);
        $expense = Expense::firstWhere('description', 'Stationery');

        $response = $this->actingAs($ken)->post(route('organizations.expenses.reverse', [$organization, $expense]));

        $response->assertRedirect();
        $this->assertSame(Expense::STATUS_REVERSED, $expense->fresh()->status);
        $this->assertSame(100000, $account->fresh()->balanceCents());
    }

    public function test_a_member_of_another_chama_cannot_view_this_chamas_expenses(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Expense Chama A');
        [, $organizationB] = $this->createChamaWithChairperson('Expense Chama B');
        $memberOfB = $this->addMemberWithRole($organizationB, 'member');

        $response = $this->actingAs($memberOfB)->get(route('organizations.expenses.index', $organizationA));

        $response->assertForbidden();
    }
}
