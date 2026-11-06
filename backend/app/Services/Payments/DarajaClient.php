<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\DarajaRequestException;
use App\Models\PaymentGatewayCredential;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin HTTP client for Safaricom's Daraja API (OAuth token exchange and STK Push initiation).
 * Holds no business logic — StkPushService decides what to do with the response.
 *
 * Every outbound call is wrapped so that a transport-level failure (DNS/connection problem, a
 * timeout, or — as actually observed while developing this against a sandboxed container with
 * no route to Safaricom — a network policy rejecting the host outright) always surfaces as a
 * DarajaRequestException, the same as a well-formed but unsuccessful Daraja response. Without
 * this, a transport exception would bubble up as an uncaught 500 instead of the friendly
 * "couldn't reach M-Pesa, try again or record manually" message StkPushController shows.
 */
class DarajaClient
{
    public function accessToken(PaymentGatewayCredential $credential): string
    {
        return Cache::remember(
            "daraja:token:{$credential->id}",
            now()->addMinutes(55),
            function () use ($credential) {
                $response = $this->request(fn () => Http::withBasicAuth($credential->consumer_key, $credential->consumer_secret)
                    ->get("{$credential->baseUrl()}/oauth/v1/generate", ['grant_type' => 'client_credentials']));

                if ($response->failed()) {
                    throw new DarajaRequestException('Unable to obtain a Daraja access token: '.$response->body());
                }

                $token = $response->json('access_token');

                if (! is_string($token) || $token === '') {
                    throw new DarajaRequestException('Daraja did not return an access token.');
                }

                return $token;
            }
        );
    }

    /**
     * @param  int  $amountShillings  Whole Kenyan shillings — Daraja's STK Push API does not
     *                                accept fractional amounts.
     * @return array<string, mixed> Daraja's decoded JSON response.
     */
    public function stkPush(
        PaymentGatewayCredential $credential,
        string $phone,
        int $amountShillings,
        string $accountReference,
        string $transactionDesc,
        string $callbackUrl,
    ): array {
        $timestamp = CarbonImmutable::now('Africa/Nairobi')->format('YmdHis');
        $password = base64_encode($credential->shortcode.$credential->passkey.$timestamp);

        $response = $this->request(fn () => Http::withToken($this->accessToken($credential))
            ->post("{$credential->baseUrl()}/mpesa/stkpush/v1/processrequest", [
                'BusinessShortCode' => $credential->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $amountShillings,
                'PartyA' => $phone,
                'PartyB' => $credential->shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => $accountReference,
                'TransactionDesc' => $transactionDesc,
            ]));

        if ($response->failed()) {
            throw new DarajaRequestException('Daraja STK Push request failed: '.$response->body());
        }

        return (array) $response->json();
    }

    /**
     * @param  \Closure(): Response  $call
     */
    private function request(\Closure $call): Response
    {
        try {
            return $call();
        } catch (DarajaRequestException $e) {
            throw $e;
        } catch (ConnectionException|RequestException $e) {
            throw new DarajaRequestException('Could not reach Safaricom: '.$e->getMessage(), previous: $e);
        } catch (Throwable $e) {
            throw new DarajaRequestException('Unexpected error talking to Safaricom: '.$e->getMessage(), previous: $e);
        }
    }
}
