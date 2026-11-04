<?php

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Support\ChamaPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Invitation::class, $this->route('organization')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(array_keys(ChamaPermissions::DEFAULT_ROLE_PERMISSIONS))],
        ];
    }
}
