<?php

namespace App\Http\Resources;

use App\Models\LoanRepayment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanRepayment
 */
class LoanRepaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => Money::format($this->amount_cents, $this->financialAccount?->currency ?? 'KES'),
            'principal_cents' => $this->principal_cents,
            'interest_cents' => $this->interest_cents,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'recorded_by' => $this->whenLoaded('recorder', fn () => $this->recorder->name),
            'status' => $this->status,
        ];
    }
}
