<?php

namespace Tests\Unit\Services\Contributions;

use App\Models\ContributionSchedule;
use App\Services\Contributions\PeriodCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PeriodCalculatorTest extends TestCase
{
    private function schedule(array $overrides = []): ContributionSchedule
    {
        $schedule = new ContributionSchedule;
        $schedule->forceFill(array_merge([
            'frequency' => ContributionSchedule::FREQUENCY_DAILY,
            'start_date' => '2026-10-21',
            'end_date' => null,
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
        ], $overrides));

        return $schedule;
    }

    public function test_daily_period_is_a_single_day(): void
    {
        $schedule = $this->schedule();
        $period = (new PeriodCalculator)->periodContaining($schedule, CarbonImmutable::parse('2026-10-23', 'Africa/Nairobi'));

        $this->assertSame('2026-10-23', $period->start->toDateString());
        $this->assertSame('2026-10-23', $period->end->toDateString());
    }

    public function test_daily_deadline_is_1159pm_in_the_schedule_timezone(): void
    {
        $schedule = $this->schedule();
        $period = (new PeriodCalculator)->periodContaining($schedule, CarbonImmutable::parse('2026-10-23', 'Africa/Nairobi'));

        $this->assertSame('2026-10-23 23:59:59', $period->dueAt->setTimezone('Africa/Nairobi')->format('Y-m-d H:i:s'));
    }

    public function test_grace_period_extends_the_deadline_by_whole_days(): void
    {
        $schedule = $this->schedule(['grace_period_days' => 2]);
        $period = (new PeriodCalculator)->periodContaining($schedule, CarbonImmutable::parse('2026-10-23', 'Africa/Nairobi'));

        $this->assertSame('2026-10-25 23:59:59', $period->graceExpiresAt->setTimezone('Africa/Nairobi')->format('Y-m-d H:i:s'));
    }

    public function test_weekly_periods_are_anchored_to_the_schedule_start_date(): void
    {
        // start_date is a Wednesday (2026-10-21); the second week should start the following Wednesday.
        $schedule = $this->schedule(['frequency' => ContributionSchedule::FREQUENCY_WEEKLY]);
        $calculator = new PeriodCalculator;

        $firstWeek = $calculator->periodContaining($schedule, CarbonImmutable::parse('2026-10-25', 'Africa/Nairobi'));
        $this->assertSame('2026-10-21', $firstWeek->start->toDateString());
        $this->assertSame('2026-10-27', $firstWeek->end->toDateString());

        $secondWeek = $calculator->periodContaining($schedule, CarbonImmutable::parse('2026-10-28', 'Africa/Nairobi'));
        $this->assertSame('2026-10-28', $secondWeek->start->toDateString());
        $this->assertSame('2026-11-03', $secondWeek->end->toDateString());
    }

    public function test_monthly_first_period_runs_from_start_date_to_end_of_that_month(): void
    {
        $schedule = $this->schedule(['frequency' => ContributionSchedule::FREQUENCY_MONTHLY, 'start_date' => '2026-10-15']);
        $period = (new PeriodCalculator)->periodContaining($schedule, CarbonImmutable::parse('2026-10-20', 'Africa/Nairobi'));

        $this->assertSame('2026-10-15', $period->start->toDateString());
        $this->assertSame('2026-10-31', $period->end->toDateString());
    }

    public function test_monthly_subsequent_periods_are_full_calendar_months(): void
    {
        $schedule = $this->schedule(['frequency' => ContributionSchedule::FREQUENCY_MONTHLY, 'start_date' => '2026-10-15']);
        $period = (new PeriodCalculator)->periodContaining($schedule, CarbonImmutable::parse('2026-11-10', 'Africa/Nairobi'));

        $this->assertSame('2026-11-01', $period->start->toDateString());
        $this->assertSame('2026-11-30', $period->end->toDateString());
    }

    public function test_monthly_handles_leap_year_february(): void
    {
        $schedule = $this->schedule(['frequency' => ContributionSchedule::FREQUENCY_MONTHLY, 'start_date' => '2028-01-01']);
        $period = (new PeriodCalculator)->periodContaining($schedule, CarbonImmutable::parse('2028-02-15', 'Africa/Nairobi'));

        // 2028 is a leap year.
        $this->assertSame('2028-02-01', $period->start->toDateString());
        $this->assertSame('2028-02-29', $period->end->toDateString());
    }

    public function test_monthly_handles_non_leap_year_february(): void
    {
        $schedule = $this->schedule(['frequency' => ContributionSchedule::FREQUENCY_MONTHLY, 'start_date' => '2026-01-01']);
        $period = (new PeriodCalculator)->periodContaining($schedule, CarbonImmutable::parse('2026-02-15', 'Africa/Nairobi'));

        $this->assertSame('2026-02-01', $period->start->toDateString());
        $this->assertSame('2026-02-28', $period->end->toDateString());
    }

    public function test_monthly_handles_year_boundary(): void
    {
        $schedule = $this->schedule(['frequency' => ContributionSchedule::FREQUENCY_MONTHLY, 'start_date' => '2026-12-01']);
        $period = (new PeriodCalculator)->periodContaining($schedule, CarbonImmutable::parse('2027-01-15', 'Africa/Nairobi'));

        $this->assertSame('2027-01-01', $period->start->toDateString());
        $this->assertSame('2027-01-31', $period->end->toDateString());
    }

    public function test_daily_periods_up_to_count_correctly_across_a_month_boundary(): void
    {
        $schedule = $this->schedule(['start_date' => '2026-10-29']);
        $periods = (new PeriodCalculator)->periodsUpTo($schedule, CarbonImmutable::parse('2026-11-02', 'Africa/Nairobi'));

        $this->assertCount(5, $periods); // Oct 29, 30, 31, Nov 1, 2
        $this->assertSame('2026-10-29', $periods[0]->start->toDateString());
        $this->assertSame('2026-11-02', $periods[4]->start->toDateString());
    }

    public function test_periods_up_to_is_bounded_by_the_schedule_end_date(): void
    {
        $schedule = $this->schedule(['start_date' => '2026-10-29', 'end_date' => '2026-10-31']);
        $periods = (new PeriodCalculator)->periodsUpTo($schedule, CarbonImmutable::parse('2026-11-05', 'Africa/Nairobi'));

        $this->assertCount(3, $periods);
        $this->assertSame('2026-10-31', $periods[2]->start->toDateString());
    }

    public function test_periods_up_to_returns_empty_before_the_schedule_starts(): void
    {
        $schedule = $this->schedule(['start_date' => '2026-10-29']);
        $periods = (new PeriodCalculator)->periodsUpTo($schedule, CarbonImmutable::parse('2026-10-20', 'Africa/Nairobi'));

        $this->assertSame([], $periods);
    }

    public function test_custom_frequency_generates_no_automatic_periods(): void
    {
        $schedule = $this->schedule(['frequency' => ContributionSchedule::FREQUENCY_CUSTOM]);
        $periods = (new PeriodCalculator)->periodsUpTo($schedule, CarbonImmutable::parse('2026-12-01', 'Africa/Nairobi'));

        $this->assertSame([], $periods);
    }
}
