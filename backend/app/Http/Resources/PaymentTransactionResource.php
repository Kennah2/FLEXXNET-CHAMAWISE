<?php

namespace App\Http\Resources;

use App\Models\PaymentTransaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentTransaction
 */
class PaymentTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone_number' => $this->phone_number,
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => Money::format($this->amount_cents, $this->currency),
            'status' => $this->status,
            'result_desc' => $this->result_desc,
            'mpesa_receipt_number' => $this->mpesa_receipt_number,
            'contribution_id' => $this->contribution_id,
            'is_terminal' => $this->isTerminal(),
        ];
    }
}
