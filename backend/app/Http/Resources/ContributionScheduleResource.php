<?php

namespace App\Http\Resources;

use App\Models\ContributionSchedule;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContributionSchedule
 */
class ContributionScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'frequency' => $this->frequency,
            'currency' => $this->currency,
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => Money::format($this->amount_cents, $this->currency),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'deadline_time' => $this->deadline_time,
            'timezone' => $this->timezone,
            'grace_period_days' => $this->grace_period_days,
            'penalty_amount_cents' => $this->penalty_amount_cents,
            'applies_to' => $this->applies_to,
            'status' => $this->status,
        ];
    }
}
