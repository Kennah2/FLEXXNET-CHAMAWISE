<?php

namespace App\Http\Requests;

use App\Models\FinancialAccount;
use Illuminate\Foundation\Http\FormRequest;

class StoreReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reconcile', $this->route('account') ?? FinancialAccount::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statement_date' => ['required', 'date', 'before_or_equal:today'],
            'statement_balance' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
