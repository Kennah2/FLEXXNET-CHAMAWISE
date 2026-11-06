<?php

namespace App\Http\Requests;

use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [WithdrawalRequest::class, $this->route('organization')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
