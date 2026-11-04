<?php

namespace App\Services\Contributions;

use Carbon\CarbonImmutable;

/**
 * One concrete period of a contribution schedule: a calendar date range, plus the exact instant
 * (timezone-aware) at which it is due and at which its grace period expires.
 */
final class ContributionPeriod
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly CarbonImmutable $dueAt,
        public readonly CarbonImmutable $graceExpiresAt,
    ) {}
}
