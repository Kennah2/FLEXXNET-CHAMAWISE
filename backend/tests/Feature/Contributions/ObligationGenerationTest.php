<?php

namespace Tests\Feature\Contributions;

use App\Models\ContributionObligation;
use App\Models\ContributionSchedule;
use App\Services\Contributions\ObligationGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

/**
 * Exercises the sample scenario from Part 11 of the specification: a two-member daily savings
 * group ("Ken and Frank Savings Group"), KES 100/day/member, KES 50 missed-contribution penalty,
 * no grace period. Time is frozen throughout — these are not the spec's literal sample dates,
 * but the same shape, so the test is not tied to the wall clock.
 */
class ObligationGenerationTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        // Freeze "now" before the schedule's start date so that every membership created in
        // these tests (which stamps joined_at = now()) is treated as joining before the
        // schedule begins, rather than after it relative to the real wall clock.
        Carbon::setTestNow(CarbonImmutable::parse('2025-12-20 00:00:00', 'Africa/Nairobi'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generates_one_obligation_per_member_per_elapsed_day_and_assesses_penalties_once_overdue(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Ken and Frank Savings Group');
        $frank = $this->addMemberWithRole($organization, 'member');

        $schedule = ContributionSchedule::create([
            'organization_id' => $organization->id,
            'name' => 'Daily savings',
            'frequency' => ContributionSchedule::FREQUENCY_DAILY,
            'currency' => 'KES',
            'amount_cents' => 10000,
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
            'penalty_amount_cents' => 5000,
            'applies_to' => ContributionSchedule::APPLIES_TO_ALL,
            'status' => ContributionSchedule::STATUS_ACTIVE,
            'created_by' => $ken->id,
        ]);

        $asOf = CarbonImmutable::parse('2026-01-04 10:00:00', 'Africa/Nairobi');
        app(ObligationGenerator::class)->generate($schedule, $asOf);

        $this->assertSame(8, ContributionObligation::where('contribution_schedule_id', $schedule->id)->count());

        foreach ([$ken, $frank] as $member) {
            $obligations = ContributionObligation::where('contribution_schedule_id', $schedule->id)
                ->where('user_id', $member->id)
                ->orderBy('period_start')
                ->get();

            $this->assertCount(4, $obligations);
            $this->assertSame(['2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'], $obligations->pluck('period_start')->map->toDateString()->all());

            // Jan 1-3 are past their 23:59 EAT deadline as of Jan 4, 10:00 EAT — overdue, penalised.
            foreach ($obligations->take(3) as $overdue) {
                $this->assertSame(ContributionObligation::STATUS_OVERDUE, $overdue->status);
                $this->assertSame(5000, $overdue->penalty_amount_cents);
                $this->assertSame(ContributionObligation::PENALTY_ASSESSED, $overdue->penalty_status);
                $this->assertSame(10000, $overdue->amount_due_cents);
            }

            // Jan 4's deadline (23:59 EAT) hasn't passed yet at 10:00 EAT — still pending, no penalty.
            $today = $obligations->last();
            $this->assertSame(ContributionObligation::STATUS_PENDING, $today->status);
            $this->assertSame(0, $today->penalty_amount_cents);
        }
    }

    public function test_due_at_survives_a_database_round_trip_in_the_schedules_timezone(): void
    {
        // Regression test: due_at/grace_expires_at must be stored so that re-reading them later
        // (a fresh query, e.g. from the next day's cron run or a page load) reproduces the same
        // wall-clock deadline in the schedule's timezone — not shifted by the zone offset.
        [$ken, $organization] = $this->createChamaWithChairperson('Timezone Round Trip Chama');

        $schedule = ContributionSchedule::create([
            'organization_id' => $organization->id,
            'name' => 'Daily savings',
            'frequency' => ContributionSchedule::FREQUENCY_DAILY,
            'currency' => 'KES',
            'amount_cents' => 10000,
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
            'penalty_amount_cents' => 0,
            'applies_to' => ContributionSchedule::APPLIES_TO_ALL,
            'status' => ContributionSchedule::STATUS_ACTIVE,
            'created_by' => $ken->id,
        ]);

        app(ObligationGenerator::class)->generate($schedule, CarbonImmutable::parse('2026-01-01 10:00:00', 'Africa/Nairobi'));

        // Fresh query — not the in-memory object generate() produced — to force a real read-back
        // through the 'datetime' cast, the only path that can reproduce the bug.
        $obligation = ContributionObligation::where('contribution_schedule_id', $schedule->id)->firstOrFail();

        $this->assertSame('2026-01-01 23:59:59', $obligation->due_at->timezone('Africa/Nairobi')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-01 23:59:59', $obligation->grace_expires_at->timezone('Africa/Nairobi')->format('Y-m-d H:i:s'));
    }

    public function test_generation_is_idempotent(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Idempotent Chama');

        $schedule = ContributionSchedule::create([
            'organization_id' => $organization->id,
            'name' => 'Daily savings',
            'frequency' => ContributionSchedule::FREQUENCY_DAILY,
            'currency' => 'KES',
            'amount_cents' => 10000,
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
            'penalty_amount_cents' => 5000,
            'applies_to' => ContributionSchedule::APPLIES_TO_ALL,
            'status' => ContributionSchedule::STATUS_ACTIVE,
            'created_by' => $ken->id,
        ]);

        $asOf = CarbonImmutable::parse('2026-01-05 10:00:00', 'Africa/Nairobi');
        $generator = app(ObligationGenerator::class);
        $generator->generate($schedule, $asOf);
        $countAfterFirst = ContributionObligation::where('contribution_schedule_id', $schedule->id)->count();

        $generator->generate($schedule, $asOf);
        $countAfterSecond = ContributionObligation::where('contribution_schedule_id', $schedule->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame(5, $countAfterFirst);
    }

    public function test_a_member_who_joins_late_only_owes_from_their_join_date(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Late Joiner Chama');

        $schedule = ContributionSchedule::create([
            'organization_id' => $organization->id,
            'name' => 'Daily savings',
            'frequency' => ContributionSchedule::FREQUENCY_DAILY,
            'currency' => 'KES',
            'amount_cents' => 10000,
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
            'penalty_amount_cents' => 0,
            'applies_to' => ContributionSchedule::APPLIES_TO_ALL,
            'status' => ContributionSchedule::STATUS_ACTIVE,
            'created_by' => $ken->id,
        ]);

        $lateJoiner = $this->addMemberWithRole(
            $organization,
            'member',
            joinedAt: CarbonImmutable::parse('2026-01-03 09:00:00', 'Africa/Nairobi')
        );

        $asOf = CarbonImmutable::parse('2026-01-05 10:00:00', 'Africa/Nairobi');
        app(ObligationGenerator::class)->generate($schedule, $asOf);

        $lateJoinerObligations = ContributionObligation::where('contribution_schedule_id', $schedule->id)
            ->where('user_id', $lateJoiner->id)
            ->orderBy('period_start')
            ->get();

        // Joined Jan 3 — no obligation for Jan 1 or Jan 2.
        $this->assertSame(['2026-01-03', '2026-01-04', '2026-01-05'], $lateJoinerObligations->pluck('period_start')->map->toDateString()->all());
    }

    public function test_a_schedule_applying_to_selected_members_only_generates_for_them(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Selected Members Chama');
        $frank = $this->addMemberWithRole($organization, 'member');

        $schedule = ContributionSchedule::create([
            'organization_id' => $organization->id,
            'name' => 'Officials only',
            'frequency' => ContributionSchedule::FREQUENCY_DAILY,
            'currency' => 'KES',
            'amount_cents' => 10000,
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
            'penalty_amount_cents' => 0,
            'applies_to' => ContributionSchedule::APPLIES_TO_SELECTED,
            'status' => ContributionSchedule::STATUS_ACTIVE,
            'created_by' => $ken->id,
        ]);
        $schedule->selectedMembers()->sync([$ken->id]);

        $asOf = CarbonImmutable::parse('2026-01-02 10:00:00', 'Africa/Nairobi');
        app(ObligationGenerator::class)->generate($schedule, $asOf);

        $this->assertSame(2, ContributionObligation::where('contribution_schedule_id', $schedule->id)->where('user_id', $ken->id)->count());
        $this->assertSame(0, ContributionObligation::where('contribution_schedule_id', $schedule->id)->where('user_id', $frank->id)->count());
    }

    public function test_a_closed_schedule_does_not_generate_new_obligations(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Closed Schedule Chama');

        $schedule = ContributionSchedule::create([
            'organization_id' => $organization->id,
            'name' => 'Daily savings',
            'frequency' => ContributionSchedule::FREQUENCY_DAILY,
            'currency' => 'KES',
            'amount_cents' => 10000,
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
            'penalty_amount_cents' => 0,
            'applies_to' => ContributionSchedule::APPLIES_TO_ALL,
            'status' => ContributionSchedule::STATUS_CLOSED,
            'created_by' => $ken->id,
        ]);

        app(ObligationGenerator::class)->generate($schedule, CarbonImmutable::parse('2026-01-05 10:00:00', 'Africa/Nairobi'));

        $this->assertSame(0, ContributionObligation::where('contribution_schedule_id', $schedule->id)->count());
    }
}
