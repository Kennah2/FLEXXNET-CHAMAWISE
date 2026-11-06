<?php

namespace Database\Seeders;

use App\Models\ChamaMembership;
use App\Models\Contribution;
use App\Models\ContributionObligation;
use App\Models\ContributionSchedule;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\ChamaRoleProvisioner;
use App\Services\Contributions\ContributionAllocator;
use App\Services\Contributions\ObligationGenerator;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Optional local-development sample data (Part 11): "Ken and Frank Savings Group", a two-member
 * daily-savings chama — KES 100/day/member, KES 50 missed-contribution penalty, no grace period.
 * These are sample settings for exercising the UI locally, not application defaults. Never run
 * automatically outside local development.
 */
class DevDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DevDataSeeder skipped: only runs in local/testing environments.');

            return;
        }

        $ken = User::firstOrCreate(
            ['email' => 'ken@example.test'],
            [
                'name' => 'Ken',
                'username' => 'ken',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $frank = User::firstOrCreate(
            ['email' => 'frank@example.test'],
            [
                'name' => 'Frank',
                'username' => 'frank',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $chama = Organization::firstOrCreate(
            ['slug' => 'ken-and-frank-savings-group'],
            [
                'name' => 'Ken and Frank Savings Group',
                'description' => 'Sample daily savings group for local development.',
                'group_type' => 'savings',
                'currency' => 'KES',
                'formation_date' => '2026-10-01',
                'status' => Organization::STATUS_ACTIVE,
                'created_by' => $ken->id,
            ]
        );

        app(ChamaRoleProvisioner::class)->provision($chama, $ken, 'chairperson');

        // Backdated to the chama's formation date, not today: both members have belonged since
        // the start, so the sample daily schedule (which also starts in the past, below) shows
        // its full history of paid/pending/overdue days rather than only "today" — a member's
        // obligations only ever start from their join date (see ObligationGenerator), so if this
        // were left as now(), the seed would look like they'd just joined.
        ChamaMembership::firstOrCreate(
            ['organization_id' => $chama->id, 'user_id' => $ken->id],
            [
                'membership_number' => ChamaMembership::nextMembershipNumber($chama->id),
                'status' => ChamaMembership::STATUS_ACTIVE,
                'joined_at' => $chama->formation_date,
            ]
        );

        $frankMembership = ChamaMembership::firstOrCreate(
            ['organization_id' => $chama->id, 'user_id' => $frank->id],
            [
                'membership_number' => ChamaMembership::nextMembershipNumber($chama->id),
                'status' => ChamaMembership::STATUS_ACTIVE,
                'joined_at' => $chama->formation_date,
            ]
        );

        if ($frankMembership->wasRecentlyCreated) {
            $frank->assignRole('member');
        }

        $schedule = ContributionSchedule::firstOrCreate(
            ['organization_id' => $chama->id, 'name' => 'Daily savings'],
            [
                'frequency' => ContributionSchedule::FREQUENCY_DAILY,
                'currency' => 'KES',
                'amount_cents' => 10000,
                'start_date' => now()->subDays(10)->toDateString(),
                'deadline_time' => '23:59',
                'timezone' => 'Africa/Nairobi',
                'grace_period_days' => 0,
                'penalty_amount_cents' => 5000,
                'applies_to' => ContributionSchedule::APPLIES_TO_ALL,
                'status' => ContributionSchedule::STATUS_ACTIVE,
                'created_by' => $ken->id,
            ]
        );

        app(ObligationGenerator::class)->generate($schedule);

        $ledgerProvisioner = app(LedgerProvisioner::class);
        $ledgerProvisioner->provisionSystemAccounts($chama);

        $mpesa = FinancialAccount::firstOrCreate(
            ['organization_id' => $chama->id, 'name' => 'M-Pesa Till'],
            [
                'type' => FinancialAccount::TYPE_MOBILE_MONEY,
                'currency' => 'KES',
                'opening_balance_cents' => 0,
                'is_default' => true,
                'created_by' => $ken->id,
            ]
        );
        $ledgerProvisioner->provisionForFinancialAccount($mpesa);

        // Verify Ken's oldest obligation, so the seed demonstrates a real posted ledger entry
        // and a non-zero account/savings balance rather than an empty ledger.
        $oldestObligation = ContributionObligation::where('contribution_schedule_id', $schedule->id)
            ->where('user_id', $ken->id)
            ->orderBy('period_start')
            ->first();

        if ($oldestObligation && Contribution::where('organization_id', $chama->id)->where('user_id', $ken->id)->doesntExist()) {
            $contribution = Contribution::create([
                'organization_id' => $chama->id,
                'user_id' => $ken->id,
                'contribution_schedule_id' => $schedule->id,
                'amount_cents' => $oldestObligation->amount_due_cents,
                'currency' => 'KES',
                'payment_method' => Contribution::METHOD_MANUAL_MPESA,
                'transaction_reference' => 'SEED1DEMO1',
                'paid_at' => $oldestObligation->period_start,
                'recorded_by' => $ken->id,
                'status' => Contribution::STATUS_PENDING_VERIFICATION,
            ]);

            $entry = app(LedgerPoster::class)->post(
                $chama,
                "Contribution verified: {$ken->name}",
                [
                    ['ledger_account_id' => $mpesa->ledgerAccount->id, 'debit_cents' => $contribution->amount_cents],
                    ['ledger_account_id' => $chama->memberSavingsAccount()->id, 'credit_cents' => $contribution->amount_cents, 'user_id' => $ken->id],
                ],
                $ken,
                source: $contribution,
            );

            $contribution->update([
                'status' => Contribution::STATUS_VERIFIED,
                'verified_by' => $ken->id,
                'verified_at' => now(),
                'financial_account_id' => $mpesa->id,
                'journal_entry_id' => $entry->id,
            ]);

            app(ContributionAllocator::class)->allocate($contribution);
        }

        $this->command?->info('Seeded sample chama: Ken and Frank Savings Group. Log in as "ken" or "frank" (or their email), password: "password".');
    }
}
