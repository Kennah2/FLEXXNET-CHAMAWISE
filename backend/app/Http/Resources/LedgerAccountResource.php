<?php

namespace App\Http\Resources;

use App\Models\LedgerAccount;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerAccount
 */
class LedgerAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'is_system' => $this->is_system,
            'balance_cents' => $this->balanceCents(),
            'balance_formatted' => Money::format($this->balanceCents(), $this->organization->currency),
        ];
    }
}
