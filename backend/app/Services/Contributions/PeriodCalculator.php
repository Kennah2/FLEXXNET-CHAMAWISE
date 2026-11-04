<?php

namespace App\Services\Contributions;

use App\Models\ContributionSchedule;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Timezone-aware period and deadline math for contribution schedules (Part 11). All date
 * arithmetic runs in the schedule's own timezone (default Africa/Nairobi) so that "due by
 * 11:59 PM" means 11:59 PM there, not UTC midnight. Carbon's own calendar math is relied on for
 * month lengths, leap years and year boundaries rather than hand-rolled day counting.
 */
class PeriodCalculator
{
    /**
     * The period that contains the given date, for this schedule's frequency.
     */
    public function periodContaining(ContributionSchedule $schedule, CarbonImmutable $date): ContributionPeriod
    {
        $date = $date->setTimezone($schedule->timezone)->startOfDay();
        $start = CarbonImmutable::parse($schedule->start_date->toDateString(), $schedule->timezone)->startOfDay();

        if ($date->lt($start)) {
            throw new InvalidArgumentException('Date is before the schedule start date.');
        }

        [$periodStart, $periodEnd] = match ($schedule->frequency) {
            ContributionSchedule::FREQUENCY_DAILY => [$date, $date],
            ContributionSchedule::FREQUENCY_WEEKLY => $this->weeklyBounds($start, $date),
            ContributionSchedule::FREQUENCY_MONTHLY => $this->monthlyBounds($start, $date),
            default => throw new InvalidArgumentException("Periods cannot be auto-calculated for frequency \"{$schedule->frequency}\"."),
        };

        $dueAt = $this->deadlineFor($periodEnd, $schedule->deadline_time);
        $graceExpiresAt = $dueAt->addDays($schedule->grace_period_days);

        return new ContributionPeriod($periodStart, $periodEnd, $dueAt, $graceExpiresAt);
    }

    /**
     * All periods from the schedule's start date up to (and including the period containing)
     * $asOf, bounded by the schedule's end date if it has one. Used to generate obligations for
     * every period that has begun so far.
     *
     * @return list<ContributionPeriod>
     */
    public function periodsUpTo(ContributionSchedule $schedule, CarbonImmutable $asOf): array
    {
        if (! in_array($schedule->frequency, [
            ContributionSchedule::FREQUENCY_DAILY,
            ContributionSchedule::FREQUENCY_WEEKLY,
            ContributionSchedule::FREQUENCY_MONTHLY,
        ], true)) {
            return [];
        }

        $start = CarbonImmutable::parse($schedule->start_date->toDateString(), $schedule->timezone)->startOfDay();
        $asOf = $asOf->setTimezone($schedule->timezone)->startOfDay();
        $hardEnd = $schedule->end_date
            ? CarbonImmutable::parse($schedule->end_date->toDateString(), $schedule->timezone)->startOfDay()
            : null;

        $cutoff = $hardEnd && $hardEnd->lt($asOf) ? $hardEnd : $asOf;

        if ($cutoff->lt($start)) {
            return [];
        }

        $periods = [];
        $cursor = $start;

        while ($cursor->lte($cutoff)) {
            $period = $this->periodContaining($schedule, $cursor);
            $periods[] = $period;
            $cursor = $period->end->addDay();
        }

        return $periods;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function weeklyBounds(CarbonImmutable $scheduleStart, CarbonImmutable $date): array
    {
        $daysSinceStart = $scheduleStart->diffInDays($date);
        $weekIndex = intdiv($daysSinceStart, 7);
        $periodStart = $scheduleStart->addDays($weekIndex * 7);

        return [$periodStart, $periodStart->addDays(6)];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function monthlyBounds(CarbonImmutable $scheduleStart, CarbonImmutable $date): array
    {
        $sameMonthAsStart = $date->year === $scheduleStart->year && $date->month === $scheduleStart->month;

        if ($sameMonthAsStart) {
            return [$scheduleStart, $scheduleStart->endOfMonth()->startOfDay()];
        }

        return [$date->startOfMonth(), $date->endOfMonth()->startOfDay()];
    }

    private function deadlineFor(CarbonImmutable $periodEnd, string $deadlineTime): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $deadlineTime));

        return $periodEnd->setTime($hour, $minute, 59);
    }
}
