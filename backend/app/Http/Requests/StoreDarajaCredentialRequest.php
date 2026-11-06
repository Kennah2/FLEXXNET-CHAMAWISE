<?php

namespace App\Http\Requests;

use App\Models\FinancialAccount;
use App\Models\PaymentGatewayCredential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDarajaCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [PaymentGatewayCredential::class, $this->route('organization')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'environment' => ['required', Rule::in([PaymentGatewayCredential::ENV_SANDBOX, PaymentGatewayCredential::ENV_PRODUCTION])],
            // Secrets are optional on update: a blank field means "keep the current value"
            // (StoreDarajaCredentialRequest never pre-fills them back into the form — see the
            // view — so there's nothing to compare against; the controller only overwrites a
            // field when it was actually typed).
            'consumer_key' => ['nullable', 'string', 'max:255'],
            'consumer_secret' => ['nullable', 'string', 'max:255'],
            'shortcode' => ['required', 'string', 'max:20'],
            'passkey' => ['nullable', 'string', 'max:500'],
            'financial_account_id' => [
                'required', 'integer',
                Rule::exists((new FinancialAccount)->getTable(), 'id')->where('organization_id', $organization->id),
            ],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }
}
