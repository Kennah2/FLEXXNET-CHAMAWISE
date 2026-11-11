<?php

namespace App\Http\Resources;

use App\Models\FinancialAccount;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialAccount
 */
class FinancialAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'account_number' => $this->account_number,
            'currency' => $this->currency,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'balance_cents' => $this->balanceCents(),
            'balance_formatted' => Money::format($this->balanceCents(), $this->currency),
        ];
    }
}
