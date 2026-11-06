<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDarajaCredentialRequest;
use App\Models\Organization;
use App\Models\PaymentGatewayCredential;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentGatewayCredentialController extends Controller
{
    public function edit(Organization $organization): View
    {
        $this->authorize('view', [PaymentGatewayCredential::class, $organization]);

        $credential = $organization->darajaCredential();
        $accounts = $organization->financialAccounts()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $callbackUrl = $credential
            ? route('api.mpesa.callback', ['organization' => $organization->id, 'token' => $credential->callback_token])
            : null;

        return view('organizations.payments.daraja', compact('organization', 'credential', 'accounts', 'callbackUrl'));
    }

    public function update(StoreDarajaCredentialRequest $request, Organization $organization, AuditLogger $auditLogger): RedirectResponse
    {
        $credential = PaymentGatewayCredential::firstOrNew([
            'organization_id' => $organization->id,
            'provider' => PaymentGatewayCredential::PROVIDER_DARAJA,
        ]);

        if (! $credential->exists) {
            $credential->callback_token = Str::random(48);
            $credential->created_by = $request->user()->id;
        }

        $credential->environment = $request->validated('environment');
        $credential->shortcode = $request->validated('shortcode');
        $credential->financial_account_id = (int) $request->validated('financial_account_id');
        $credential->updated_by = $request->user()->id;

        // A blank secret field means "keep what's already saved" — the form never pre-fills
        // these back in, so an empty submission is never mistaken for "clear the secret".
        if ($request->filled('consumer_key')) {
            $credential->consumer_key = $request->validated('consumer_key');
        }
        if ($request->filled('consumer_secret')) {
            $credential->consumer_secret = $request->validated('consumer_secret');
        }
        if ($request->filled('passkey')) {
            $credential->passkey = $request->validated('passkey');
        }

        $wantsEnabled = $request->boolean('is_enabled');
        $credential->is_enabled = $wantsEnabled && $credential->isConfigured();

        $credential->save();

        $auditLogger->log(
            'payment_gateway_credential.updated',
            $credential,
            organizationId: $organization->id,
            // Never log secret values themselves (Part 36) — only which non-secret fields changed.
            after: $credential->only(['environment', 'shortcode', 'financial_account_id', 'is_enabled']),
        );

        $status = $wantsEnabled && ! $credential->is_enabled
            ? 'Saved, but M-Pesa collection was not enabled — consumer key, consumer secret, shortcode, passkey and a financial account are all required first.'
            : 'M-Pesa settings saved.';

        return redirect()
            ->route('organizations.payments.daraja.edit', $organization)
            ->with('status', $status);
    }
}
