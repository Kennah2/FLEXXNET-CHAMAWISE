<?php

namespace Database\Seeders;

use App\Models\ChamaMembership;
use App\Models\ContributionSchedule;
use App\Models\Organization;
use App\Models\User;
use App\Services\ChamaRoleProvisioner;
use App\Services\Contributions\ObligationGenerator;
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

        $this->command?->info('Seeded sample chama: Ken and Frank Savings Group. Log in as "ken" or "frank" (or their email), password: "password".');
    }
}
