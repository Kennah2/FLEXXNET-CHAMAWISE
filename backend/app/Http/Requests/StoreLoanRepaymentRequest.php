<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordRepayment', $this->route('loan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }
}
