<?php

namespace App\Http\Requests;

use App\Models\FinancialAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [FinancialAccount::class, $this->route('organization')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(FinancialAccount::TYPES)],
            'account_number' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
