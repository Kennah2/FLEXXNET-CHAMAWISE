<?php

namespace App\Http\Resources;

use App\Models\ContributionObligation;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContributionObligation
 */
class ContributionObligationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schedule_id' => $this->contribution_schedule_id,
            'schedule_name' => $this->whenLoaded('schedule', fn () => $this->schedule->name),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'due_at' => $this->due_at?->toIso8601String(),
            'amount_due_cents' => $this->amount_due_cents,
            'amount_paid_cents' => $this->amount_paid_cents,
            'outstanding_cents' => $this->outstandingCents(),
            'outstanding_formatted' => Money::format($this->outstandingCents(), $this->schedule?->currency ?? 'KES'),
            'status' => $this->status,
            'penalty_amount_cents' => $this->penalty_amount_cents,
            'penalty_status' => $this->penalty_status,
        ];
    }
}
