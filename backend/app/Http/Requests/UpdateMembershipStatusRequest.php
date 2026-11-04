<?php

namespace App\Http\Requests;

use App\Models\ChamaMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('membership'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                ChamaMembership::STATUS_ACTIVE,
                ChamaMembership::STATUS_SUSPENDED,
                ChamaMembership::STATUS_REMOVED,
            ])],
        ];
    }
}
