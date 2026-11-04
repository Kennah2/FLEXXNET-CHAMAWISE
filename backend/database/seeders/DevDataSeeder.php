<?php

namespace Database\Seeders;

use App\Models\ChamaMembership;
use App\Models\Organization;
use App\Models\User;
use App\Services\ChamaRoleProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Optional local-development sample data (Part 11): "Ken and Frank Savings Group", a two-member
 * daily-savings chama. These are sample settings for exercising the UI locally — not application
 * defaults. Contribution schedules/obligations themselves are built in Phase 2; here we only
 * seed the chama, its two members, and the intended contribution rules as organization settings
 * for Phase 2 to read. Never run automatically outside local development.
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
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $frank = User::firstOrCreate(
            ['email' => 'frank@example.test'],
            [
                'name' => 'Frank',
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
                'settings' => [
                    'contribution' => [
                        'frequency' => 'daily',
                        'amount_per_member' => 100,
                        'expected_group_total' => 200,
                        'start_date' => '2026-10-21',
                        'withdrawal_date' => '2027-01-21',
                        'deadline_time' => '23:59',
                        'timezone' => 'Africa/Nairobi',
                        'grace_period_days' => 0,
                        'missed_contribution_penalty' => 50,
                    ],
                ],
            ]
        );

        app(ChamaRoleProvisioner::class)->provision($chama, $ken, 'chairperson');

        ChamaMembership::firstOrCreate(
            ['organization_id' => $chama->id, 'user_id' => $ken->id],
            [
                'membership_number' => ChamaMembership::nextMembershipNumber($chama->id),
                'status' => ChamaMembership::STATUS_ACTIVE,
                'joined_at' => now(),
            ]
        );

        $frankMembership = ChamaMembership::firstOrCreate(
            ['organization_id' => $chama->id, 'user_id' => $frank->id],
            [
                'membership_number' => ChamaMembership::nextMembershipNumber($chama->id),
                'status' => ChamaMembership::STATUS_ACTIVE,
                'joined_at' => now(),
            ]
        );

        if ($frankMembership->wasRecentlyCreated) {
            $frank->assignRole('member');
        }

        $this->command?->info('Seeded sample chama: Ken and Frank Savings Group (ken@example.test / frank@example.test, password: "password").');
    }
}
