<?php

namespace App\Http\Requests;

use App\Models\Contribution;
use Illuminate\Foundation\Http\FormRequest;

class InitiateStkPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        $forUserId = (int) ($this->input('user_id') ?: $this->user()->id);

        return $this->user()->can('create', [Contribution::class, $this->route('organization'), $forUserId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer'],
            'contribution_schedule_id' => ['nullable', 'integer', 'exists:contribution_schedules,id'],
            // Whole shillings only — Daraja's STK Push API does not accept a fractional Amount,
            // unlike manual recording (StoreContributionRequest), which allows cents.
            'amount' => ['required', 'regex:/^\d+$/', 'integer', 'min:1'],
            'phone' => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => 'M-Pesa STK Push only accepts whole shilling amounts (no cents).',
        ];
    }
}
