<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\DarajaNotConfiguredException;
use App\Exceptions\Payments\DarajaRequestException;
use App\Http\Requests\InitiateStkPushRequest;
use App\Models\Organization;
use App\Models\PaymentTransaction;
use App\Services\Payments\StkPushService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class StkPushController extends Controller
{
    public function initiate(InitiateStkPushRequest $request, Organization $organization, StkPushService $service): JsonResponse|RedirectResponse
    {
        $forUserId = (int) ($request->validated('user_id') ?: $request->user()->id);
        $scheduleId = $request->validated('contribution_schedule_id');

        try {
            $transaction = $service->initiate(
                $organization,
                $request->user(),
                $forUserId,
                Money::parseToCents($request->validated('amount')),
                $request->validated('phone'),
                $scheduleId !== null ? (int) $scheduleId : null,
            );
        } catch (DarajaNotConfiguredException|DarajaRequestException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($transaction->status === PaymentTransaction::STATUS_FAILED) {
            $message = 'M-Pesa declined the request: '.($transaction->result_desc ?? 'unknown reason').'.';

            if ($request->wantsJson()) {
                return response()->json(['transaction_id' => $transaction->id, 'status' => $transaction->status, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $message = 'Check your phone and enter your M-Pesa PIN to complete the payment.';

        if ($request->wantsJson()) {
            return response()->json(['transaction_id' => $transaction->id, 'status' => $transaction->status, 'message' => $message]);
        }

        return back()->with('status', $message);
    }

    public function status(Organization $organization, PaymentTransaction $transaction): JsonResponse
    {
        abort_unless($transaction->organization_id === $organization->id, 404);
        $this->authorize('view', $transaction);

        return response()->json([
            'transaction_id' => $transaction->id,
            'status' => $transaction->status,
            'result_desc' => $transaction->result_desc,
            'is_terminal' => $transaction->isTerminal(),
        ]);
    }
}
