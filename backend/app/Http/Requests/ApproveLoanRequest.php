<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('loan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approved_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            // A percentage, e.g. "12.5" for 12.5% flat interest over the full term. Parsed via
            // Money::parseToCents (not a float) because a basis-point figure — hundredths of a
            // percent — has exactly the same two-decimal-shift structure as cents — hundredths
            // of a currency unit — so the same exact, float-free parser applies unchanged.
            'interest_rate' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }
}
