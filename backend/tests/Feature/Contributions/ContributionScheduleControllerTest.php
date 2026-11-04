<?php

namespace Tests\Feature\Contributions;

use App\Models\ContributionSchedule;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class ContributionScheduleControllerTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_chairperson_can_create_a_contribution_schedule(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Schedule Chama');

        $response = $this->actingAs($ken)->post(route('organizations.contributions.schedules.store', $organization), [
            'name' => 'Daily savings',
            'frequency' => 'daily',
            'amount' => '100.00',
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'grace_period_days' => 0,
            'penalty_amount' => '50.00',
            'applies_to' => 'all',
        ]);

        $schedule = ContributionSchedule::firstWhere('name', 'Daily savings');
        $response->assertRedirect(route('organizations.contributions.schedules.show', [$organization, $schedule]));
        $this->assertNotNull($schedule);
        $this->assertSame(10000, $schedule->amount_cents);
        $this->assertSame(5000, $schedule->penalty_amount_cents);
    }

    public function test_an_ordinary_member_cannot_create_a_contribution_schedule(): void
    {
        [, $organization] = $this->createChamaWithChairperson('Member Cannot Create Chama');
        $member = $this->addMemberWithRole($organization, 'member');

        $response = $this->actingAs($member)->post(route('organizations.contributions.schedules.store', $organization), [
            'name' => 'Daily savings',
            'frequency' => 'daily',
            'amount' => '100.00',
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'grace_period_days' => 0,
            'applies_to' => 'all',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('contribution_schedules', ['name' => 'Daily savings']);
    }

    public function test_a_treasurer_can_create_a_contribution_schedule(): void
    {
        [, $organization] = $this->createChamaWithChairperson('Treasurer Can Create Chama');
        $treasurer = $this->addMemberWithRole($organization, 'treasurer');

        $response = $this->actingAs($treasurer)->post(route('organizations.contributions.schedules.store', $organization), [
            'name' => 'Daily savings',
            'frequency' => 'daily',
            'amount' => '100.00',
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'grace_period_days' => 0,
            'applies_to' => 'all',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contribution_schedules', ['name' => 'Daily savings']);
    }

    public function test_a_member_of_another_chama_cannot_view_this_chamas_schedule(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Chama A');
        [, $organizationB] = $this->createChamaWithChairperson('Chama B');

        $schedule = ContributionSchedule::create([
            'organization_id' => $organizationA->id,
            'name' => 'Daily savings',
            'frequency' => 'daily',
            'currency' => 'KES',
            'amount_cents' => 10000,
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
            'penalty_amount_cents' => 0,
            'applies_to' => 'all',
            'status' => 'active',
            'created_by' => $ken->id,
        ]);

        $memberOfB = $this->addMemberWithRole($organizationB, 'member');

        $response = $this->actingAs($memberOfB)->get(route('organizations.contributions.schedules.show', [$organizationA, $schedule]));

        $response->assertForbidden();
    }
}
