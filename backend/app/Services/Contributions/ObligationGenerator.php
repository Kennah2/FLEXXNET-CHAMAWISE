<?php

namespace App\Services\Contributions;

use App\Models\ContributionObligation;
use App\Models\ContributionSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates the ContributionObligation row for every (member, period) pair that has begun as of
 * "now", for every applicable member of a schedule, and refreshes each obligation's overdue /
 * penalty state. Idempotent — safe to call repeatedly (once per page view, and again from the
 * scheduled command), per docs/SHARED_HOSTING_DEPLOYMENT.md's guidance not to rely on cron
 * firing at an exact moment.
 */
class ObligationGenerator
{
    public function __construct(private readonly PeriodCalculator $periods) {}

    public function generate(ContributionSchedule $schedule, ?CarbonImmutable $asOf = null): void
    {
        if (! $schedule->isActive()) {
            return;
        }

        $asOf ??= CarbonImmutable::now();
        $periods = $this->periods->periodsUpTo($schedule, $asOf);

        if ($periods === []) {
            return;
        }

        $members = $schedule->applicableMembers();

        DB::transaction(function () use ($schedule, $periods, $members, $asOf) {
            foreach ($members as $member) {
                $memberFrom = $member->pivot->joined_at
                    ? CarbonImmutable::parse($member->pivot->joined_at)->setTimezone($schedule->timezone)->startOfDay()
                    : null;

                foreach ($periods as $period) {
                    if ($memberFrom && $period->start->lt($memberFrom)) {
                        continue;
                    }

                    $obligation = ContributionObligation::firstOrCreate(
                        [
                            'contribution_schedule_id' => $schedule->id,
                            'user_id' => $member->id,
                            'period_start' => $period->start->toDateString(),
                        ],
                        [
                            'organization_id' => $schedule->organization_id,
                            'period_end' => $period->end->toDateString(),
                            // Eloquent's datetime cast serializes a Carbon value by printing
                            // whatever wall-clock digits it currently holds — it does not convert
                            // to the app's UTC timezone first. $period->dueAt carries the
                            // schedule's own timezone (e.g. Africa/Nairobi), so saving it as-is
                            // would store "23:59" with no timezone marker and then, on the next
                            // read, Eloquent reinterprets those same digits as UTC — silently
                            // shifting the deadline by the zone offset on every round-trip through
                            // the database. Converting to UTC here keeps the stored instant
                            // correct; display code converts back to the schedule's timezone.
                            'due_at' => $period->dueAt->utc(),
                            'grace_expires_at' => $period->graceExpiresAt->utc(),
                            'amount_due_cents' => $schedule->amount_cents,
                            'amount_paid_cents' => 0,
                            'status' => ContributionObligation::STATUS_PENDING,
                            'penalty_amount_cents' => 0,
                            // Explicit, not left to the DB column default: a freshly `create()`d
                            // model doesn't reflect a DB-applied default in its in-memory
                            // attributes, so leaving this out would make the very next
                            // `penalty_status === PENALTY_NONE` check below compare against null.
                            'penalty_status' => ContributionObligation::PENALTY_NONE,
                        ]
                    );

                    $this->refreshStatus($obligation, $asOf, $schedule);
                }
            }
        });
    }

    /**
     * Recomputes an obligation's status (and assesses its penalty, if newly overdue) against the
     * given instant. Never touches amount_paid_cents — that only ever changes through
     * ContributionAllocator.
     */
    public function refreshStatus(ContributionObligation $obligation, ?CarbonImmutable $asOf = null, ?ContributionSchedule $schedule = null): void
    {
        $asOf ??= CarbonImmutable::now();
        $schedule ??= $obligation->schedule;

        if ($obligation->status === ContributionObligation::STATUS_WAIVED) {
            return;
        }

        $status = match (true) {
            $obligation->isFullyPaid() => ContributionObligation::STATUS_PAID,
            $obligation->amount_paid_cents > 0 => ContributionObligation::STATUS_PARTIAL,
            $asOf->gt($obligation->grace_expires_at) => ContributionObligation::STATUS_OVERDUE,
            default => ContributionObligation::STATUS_PENDING,
        };

        $dirty = false;

        if ($obligation->status !== $status) {
            $obligation->status = $status;
            $dirty = true;
        }

        if ($status === ContributionObligation::STATUS_OVERDUE
            && $obligation->penalty_status === ContributionObligation::PENALTY_NONE
            && $schedule->penalty_amount_cents > 0) {
            $obligation->penalty_amount_cents = $schedule->penalty_amount_cents;
            $obligation->penalty_status = ContributionObligation::PENALTY_ASSESSED;
            $dirty = true;
        }

        if ($dirty) {
            $obligation->save();
        }
    }
}
