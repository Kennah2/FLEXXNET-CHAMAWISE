<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisburseLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('disburse', $this->route('loan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'integer'],
        ];
    }
}
