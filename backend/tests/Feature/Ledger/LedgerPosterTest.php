<?php

namespace Tests\Feature\Ledger;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerPosting;
use App\Models\Organization;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class LedgerPosterTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function cashAndSavingsAccounts(Organization $organization): array
    {
        app(LedgerProvisioner::class)->provisionSystemAccounts($organization);

        $cash = LedgerAccount::create([
            'organization_id' => $organization->id,
            'code' => 'test_cash',
            'name' => 'Test Cash',
            'type' => LedgerAccount::TYPE_ASSET,
        ]);

        $savings = $organization->memberSavingsAccount();

        return [$cash, $savings];
    }

    public function test_a_balanced_entry_posts_and_updates_both_account_balances(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Ledger Chama');
        [$cash, $savings] = $this->cashAndSavingsAccounts($organization);

        $entry = app(LedgerPoster::class)->post(
            $organization,
            'Ken contributes 100',
            [
                ['ledger_account_id' => $cash->id, 'debit_cents' => 10000],
                ['ledger_account_id' => $savings->id, 'credit_cents' => 10000, 'user_id' => $ken->id],
            ],
            $ken,
        );

        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertSame(2, $entry->postings()->count());
        $this->assertSame(10000, $cash->fresh()->balanceCents());
        $this->assertSame(10000, $savings->fresh()->balanceCents());
        $this->assertSame(10000, $savings->fresh()->balanceCents($ken->id));
    }

    public function test_an_unbalanced_entry_is_rejected_and_nothing_is_persisted(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Unbalanced Chama');
        [$cash, $savings] = $this->cashAndSavingsAccounts($organization);

        $this->expectException(InvalidArgumentException::class);

        try {
            app(LedgerPoster::class)->post(
                $organization,
                'Broken entry',
                [
                    ['ledger_account_id' => $cash->id, 'debit_cents' => 10000],
                    ['ledger_account_id' => $savings->id, 'credit_cents' => 9000],
                ],
                $ken,
            );
        } finally {
            $this->assertSame(0, JournalEntry::count());
            $this->assertSame(0, LedgerPosting::count());
        }
    }

    public function test_a_line_with_both_debit_and_credit_is_rejected(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Both Sides Chama');
        [$cash, $savings] = $this->cashAndSavingsAccounts($organization);

        $this->expectException(InvalidArgumentException::class);

        app(LedgerPoster::class)->post(
            $organization,
            'Bad line',
            [
                ['ledger_account_id' => $cash->id, 'debit_cents' => 100, 'credit_cents' => 100],
                ['ledger_account_id' => $savings->id, 'credit_cents' => 0],
            ],
            $ken,
        );
    }

    public function test_negative_amounts_are_rejected(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Negative Chama');
        [$cash, $savings] = $this->cashAndSavingsAccounts($organization);

        $this->expectException(InvalidArgumentException::class);

        app(LedgerPoster::class)->post(
            $organization,
            'Negative line',
            [
                ['ledger_account_id' => $cash->id, 'debit_cents' => -100],
                ['ledger_account_id' => $savings->id, 'credit_cents' => -100],
            ],
            $ken,
        );
    }

    public function test_a_ledger_account_from_another_organization_is_rejected(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Org A');
        [, $organizationB] = $this->createChamaWithChairperson('Org B');
        [$cashA] = $this->cashAndSavingsAccounts($organizationA);
        [, $savingsB] = $this->cashAndSavingsAccounts($organizationB);

        $this->expectException(InvalidArgumentException::class);

        app(LedgerPoster::class)->post(
            $organizationA,
            'Cross-tenant leak attempt',
            [
                ['ledger_account_id' => $cashA->id, 'debit_cents' => 100],
                ['ledger_account_id' => $savingsB->id, 'credit_cents' => 100],
            ],
            $ken,
        );
    }

    public function test_reversing_an_entry_restores_balances_to_zero_and_marks_the_original_reversed(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Reverse Ledger Chama');
        [$cash, $savings] = $this->cashAndSavingsAccounts($organization);

        $poster = app(LedgerPoster::class);
        $entry = $poster->post(
            $organization,
            'Ken contributes 100',
            [
                ['ledger_account_id' => $cash->id, 'debit_cents' => 10000],
                ['ledger_account_id' => $savings->id, 'credit_cents' => 10000, 'user_id' => $ken->id],
            ],
            $ken,
        );

        $reversal = $poster->reverse($entry, $ken);

        $this->assertSame(JournalEntry::STATUS_REVERSED, $entry->fresh()->status);
        $this->assertSame($entry->id, $reversal->reverses_journal_entry_id);
        $this->assertSame(0, $cash->fresh()->balanceCents());
        $this->assertSame(0, $savings->fresh()->balanceCents($ken->id));

        // The original postings are untouched — only a new, mirrored entry was added.
        $this->assertSame(4, LedgerPosting::count());
    }

    public function test_an_already_reversed_entry_cannot_be_reversed_again(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Double Reverse Chama');
        [$cash, $savings] = $this->cashAndSavingsAccounts($organization);

        $poster = app(LedgerPoster::class);
        $entry = $poster->post(
            $organization,
            'Entry',
            [
                ['ledger_account_id' => $cash->id, 'debit_cents' => 5000],
                ['ledger_account_id' => $savings->id, 'credit_cents' => 5000, 'user_id' => $ken->id],
            ],
            $ken,
        );
        $poster->reverse($entry, $ken);

        $this->expectException(InvalidArgumentException::class);
        $poster->reverse($entry->fresh(), $ken);
    }

    public function test_member_sub_ledger_balances_are_isolated_per_member(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Sub Ledger Chama');
        $frank = $this->addMemberWithRole($organization, 'member');
        [$cash, $savings] = $this->cashAndSavingsAccounts($organization);

        $poster = app(LedgerPoster::class);
        $poster->post($organization, 'Ken 100', [
            ['ledger_account_id' => $cash->id, 'debit_cents' => 10000],
            ['ledger_account_id' => $savings->id, 'credit_cents' => 10000, 'user_id' => $ken->id],
        ], $ken);
        $poster->post($organization, 'Frank 150', [
            ['ledger_account_id' => $cash->id, 'debit_cents' => 15000],
            ['ledger_account_id' => $savings->id, 'credit_cents' => 15000, 'user_id' => $frank->id],
        ], $ken);

        $this->assertSame(10000, $savings->fresh()->balanceCents($ken->id));
        $this->assertSame(15000, $savings->fresh()->balanceCents($frank->id));
        $this->assertSame(25000, $savings->fresh()->balanceCents());
    }
}
