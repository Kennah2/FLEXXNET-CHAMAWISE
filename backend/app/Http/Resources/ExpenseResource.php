<?php

namespace App\Http\Resources;

use App\Models\Expense;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'financial_account_id' => $this->financial_account_id,
            'financial_account_name' => $this->whenLoaded('financialAccount', fn () => $this->financialAccount->name),
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => Money::format($this->amount_cents, $this->financialAccount?->currency ?? 'KES'),
            'description' => $this->description,
            'expense_date' => $this->expense_date?->toDateString(),
            'status' => $this->status,
            'recorded_by' => new UserResource($this->whenLoaded('recorder')),
        ];
    }
}
