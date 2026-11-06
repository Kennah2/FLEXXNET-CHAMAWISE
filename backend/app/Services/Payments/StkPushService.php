<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\DarajaNotConfiguredException;
use App\Exceptions\Payments\DarajaRequestException;
use App\Models\Organization;
use App\Models\PaymentGatewayCredential;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Str;

/**
 * Starts an STK Push collection attempt. Creates the PaymentTransaction row before calling
 * Safaricom so every attempt is recorded even if the outbound request itself fails, then calls
 * Daraja and records whatever it reports. Initiating a push is never itself proof of payment —
 * only DarajaCallbackHandler, reacting to Safaricom's own callback, can mark one completed.
 */
class StkPushService
{
    public function __construct(private readonly DarajaClient $client) {}

    public function initiate(
        Organization $organization,
        User $actor,
        int $forUserId,
        int $amountCents,
        string $phone,
        ?int $contributionScheduleId,
    ): PaymentTransaction {
        $credential = PaymentGatewayCredential::where('organization_id', $organization->id)
            ->where('provider', PaymentGatewayCredential::PROVIDER_DARAJA)
            ->where('is_enabled', true)
            ->first();

        if (! $credential || ! $credential->isConfigured()) {
            throw new DarajaNotConfiguredException("M-Pesa payments aren't set up for {$organization->name} yet — record the payment manually instead.");
        }

        $normalizedPhone = PhoneNumber::normalizeKenyan($phone);

        $transaction = PaymentTransaction::create([
            'organization_id' => $organization->id,
            'user_id' => $forUserId,
            'initiated_by' => $actor->id,
            'contribution_schedule_id' => $contributionScheduleId,
            'financial_account_id' => $credential->financial_account_id,
            'phone_number' => $normalizedPhone,
            'amount_cents' => $amountCents,
            'currency' => $organization->currency,
            'provider' => PaymentTransaction::PROVIDER_DARAJA,
            'status' => PaymentTransaction::STATUS_INITIATED,
        ]);

        $callbackUrl = route('api.mpesa.callback', [
            'organization' => $organization->id,
            'token' => $credential->callback_token,
        ]);

        try {
            $response = $this->client->stkPush(
                $credential,
                $normalizedPhone,
                intdiv($amountCents, 100),
                accountReference: Str::limit(preg_replace('/[^A-Za-z0-9 ]/', '', $organization->name) ?? 'CHAMAWISE', 12, ''),
                transactionDesc: 'Chama contribution',
                callbackUrl: $callbackUrl,
            );
        } catch (DarajaRequestException $e) {
            $transaction->update([
                'status' => PaymentTransaction::STATUS_FAILED,
                'result_desc' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ((string) ($response['ResponseCode'] ?? '1') !== '0') {
            $transaction->update([
                'status' => PaymentTransaction::STATUS_FAILED,
                'result_desc' => $response['ResponseDescription'] ?? $response['errorMessage'] ?? 'Daraja rejected the request.',
            ]);

            return $transaction;
        }

        $transaction->update([
            'status' => PaymentTransaction::STATUS_PENDING,
            'merchant_request_id' => $response['MerchantRequestID'] ?? null,
            'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
        ]);

        return $transaction;
    }
}
