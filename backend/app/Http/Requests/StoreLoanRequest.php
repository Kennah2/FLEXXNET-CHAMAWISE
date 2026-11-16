<?php

namespace App\Http\Requests;

use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $forUserId = (int) ($this->input('user_id') ?: $this->user()->id);

        return $this->user()->can('create', [Loan::class, $this->route('organization'), $forUserId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
