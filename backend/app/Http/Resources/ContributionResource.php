<?php

namespace App\Http\Resources;

use App\Models\Contribution;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contribution
 */
class ContributionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'schedule_id' => $this->contribution_schedule_id,
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => Money::format($this->amount_cents, $this->currency),
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->paymentMethodLabel(),
            'transaction_reference' => $this->transaction_reference,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'status' => $this->status,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'financial_account_id' => $this->financial_account_id,
            'journal_entry_id' => $this->journal_entry_id,
            'notes' => $this->notes,
        ];
    }
}
