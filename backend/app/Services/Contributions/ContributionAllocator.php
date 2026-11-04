<?php

namespace App\Services\Contributions;

use App\Models\Contribution;
use App\Models\ContributionAllocation;
use App\Models\ContributionObligation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies a verified payment against the member's outstanding obligations, oldest period first
 * (FIFO), and supports reversing that application later without destroying the audit trail
 * (Part 12 — correct errors through reversal, never silent overwrite/delete).
 */
class ContributionAllocator
{
    public function __construct(private readonly ObligationGenerator $obligations) {}

    /**
     * Allocates a just-verified contribution across the member's outstanding obligations. If the
     * contribution is tied to a specific schedule, only that schedule's obligations are eligible;
     * otherwise any of the member's outstanding obligations in this organization are. Any amount
     * left over after every outstanding obligation is fully covered stays unallocated on the
     * contribution — a surplus, not silently applied to periods that don't exist yet.
     */
    public function allocate(Contribution $contribution): void
    {
        DB::transaction(function () use ($contribution) {
            $query = ContributionObligation::query()
                ->where('organization_id', $contribution->organization_id)
                ->where('user_id', $contribution->user_id)
                ->whereIn('status', [
                    ContributionObligation::STATUS_PENDING,
                    ContributionObligation::STATUS_PARTIAL,
                    ContributionObligation::STATUS_OVERDUE,
                ])
                ->orderBy('period_start');

            if ($contribution->contribution_schedule_id) {
                $query->where('contribution_schedule_id', $contribution->contribution_schedule_id);
            }

            $remaining = $contribution->amount_cents;

            foreach ($query->lockForUpdate()->get() as $obligation) {
                if ($remaining <= 0) {
                    break;
                }

                $portion = min($remaining, $obligation->outstandingCents());

                if ($portion <= 0) {
                    continue;
                }

                ContributionAllocation::create([
                    'contribution_id' => $contribution->id,
                    'contribution_obligation_id' => $obligation->id,
                    'amount_cents' => $portion,
                ]);

                $obligation->amount_paid_cents += $portion;
                $this->obligations->refreshStatus($obligation);

                $remaining -= $portion;
            }
        });
    }

    /**
     * Reverses every allocation a (previously verified) contribution made, restoring each
     * affected obligation's paid amount and status. The allocation rows are kept and stamped
     * with reversed_at rather than deleted.
     */
    public function reverse(Contribution $contribution): void
    {
        DB::transaction(function () use ($contribution) {
            $allocations = $contribution->allocations()->whereNull('reversed_at')->lockForUpdate()->get();

            foreach ($allocations as $allocation) {
                $obligation = $allocation->obligation()->lockForUpdate()->first();
                $obligation->amount_paid_cents = max(0, $obligation->amount_paid_cents - $allocation->amount_cents);
                $this->obligations->refreshStatus($obligation, CarbonImmutable::now());

                $allocation->reversed_at = now();
                $allocation->save();
            }
        });
    }
}
