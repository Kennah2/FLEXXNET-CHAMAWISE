<?php

namespace App\Http\Resources;

use App\Models\Loan;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Loan
 */
class LoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'requested_amount_cents' => $this->requested_amount_cents,
            'requested_amount_formatted' => Money::format($this->requested_amount_cents, $this->organization->currency ?? 'KES'),
            'requested_term_months' => $this->requested_term_months,
            'reason' => $this->reason,
            'status' => $this->status,
            'approved_amount_cents' => $this->approved_amount_cents,
            'approved_amount_formatted' => $this->approved_amount_cents !== null
                ? Money::format($this->approved_amount_cents, $this->organization->currency ?? 'KES')
                : null,
            'term_months' => $this->term_months,
            'interest_rate_percent' => $this->interest_rate_bps !== null ? $this->interest_rate_bps / 100 : null,
            'outstanding_principal_cents' => $this->relationLoaded('installments') ? $this->outstandingPrincipalCents() : null,
            'outstanding_interest_cents' => $this->relationLoaded('installments') ? $this->outstandingInterestCents() : null,
            'disbursed_at' => $this->disbursed_at?->toIso8601String(),
            'installments' => LoanInstallmentResource::collection($this->whenLoaded('installments')),
            'repayments' => LoanRepaymentResource::collection($this->whenLoaded('repayments')),
        ];
    }
}
