<?php

namespace App\Http\Resources;

use App\Models\LoanInstallment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanInstallment
 */
class LoanInstallmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date?->toDateString(),
            'principal_due_cents' => $this->principal_due_cents,
            'interest_due_cents' => $this->interest_due_cents,
            'principal_paid_cents' => $this->principal_paid_cents,
            'interest_paid_cents' => $this->interest_paid_cents,
            'outstanding_cents' => $this->outstandingCents(),
            'status' => $this->status,
        ];
    }
}
