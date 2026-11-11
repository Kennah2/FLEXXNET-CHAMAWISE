<?php

namespace App\Http\Resources;

use App\Models\AccountReconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountReconciliation
 */
class AccountReconciliationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statement_date' => $this->statement_date?->toDateString(),
            'statement_balance_cents' => $this->statement_balance_cents,
            'computed_balance_cents' => $this->computed_balance_cents,
            'variance_cents' => $this->variance_cents,
            'is_balanced' => $this->isBalanced(),
            'notes' => $this->notes,
        ];
    }
}
