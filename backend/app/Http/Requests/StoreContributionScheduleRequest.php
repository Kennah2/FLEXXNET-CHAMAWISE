<?php

namespace App\Http\Requests;

use App\Models\ContributionSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContributionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [ContributionSchedule::class, $this->route('organization')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'frequency' => ['required', Rule::in(ContributionSchedule::FREQUENCIES)],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'deadline_time' => ['required', 'date_format:H:i'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:30'],
            'penalty_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'applies_to' => ['required', Rule::in([ContributionSchedule::APPLIES_TO_ALL, ContributionSchedule::APPLIES_TO_SELECTED])],
            'member_ids' => ['required_if:applies_to,selected', 'array'],
            'member_ids.*' => ['integer'],
        ];
    }
}
