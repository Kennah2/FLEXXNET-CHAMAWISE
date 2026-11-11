<?php

namespace App\Http\Resources;

use App\Models\WithdrawalRequest;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WithdrawalRequest
 */
class WithdrawalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'financial_account_id' => $this->financial_account_id,
            'financial_account_name' => $this->whenLoaded('financialAccount', fn () => $this->financialAccount->name),
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => Money::format($this->amount_cents, $this->financialAccount?->currency ?? 'KES'),
            'reason' => $this->reason,
            'required_approvals' => $this->required_approvals,
            'approvals_count' => $this->approvalsCount(),
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
