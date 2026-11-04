<?php

namespace App\Http\Requests;

use App\Models\Contribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContributionRequest extends FormRequest
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
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_method' => ['required', Rule::in(Contribution::METHODS)],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
