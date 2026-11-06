<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PaymentGatewayCredential;
use App\Services\Payments\DarajaCallbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Safaricom's STK Push result callback. Unauthenticated by necessity (Safaricom sends
 * no bearer token), so authenticity instead rests on the unguessable per-chama {token} segment
 * of the callback URL — see docs/SECURITY.md for why this, rather than IP allowlisting, is the
 * primary guard (Safaricom's public Daraja API does not sign callback payloads).
 *
 * Always responds 200 with a Daraja-shaped acknowledgement, even when the payload is rejected or
 * ignored — Safaricom retries a callback it doesn't get a 200 for, and we never want that retry
 * storm for a request that was correctly recognized as invalid.
 */
class DarajaCallbackController extends Controller
{
    public function handle(Request $request, int $organization, string $token, DarajaCallbackHandler $handler): JsonResponse
    {
        $ack = response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

        $org = Organization::find($organization);
        $credential = $org
            ? PaymentGatewayCredential::where('organization_id', $org->id)
                ->where('provider', PaymentGatewayCredential::PROVIDER_DARAJA)
                ->first()
            : null;

        if (! $org || ! $credential || ! hash_equals($credential->callback_token, $token)) {
            return $ack;
        }

        $handler->handle($org, (array) $request->json()->all());

        return $ack;
    }
}
