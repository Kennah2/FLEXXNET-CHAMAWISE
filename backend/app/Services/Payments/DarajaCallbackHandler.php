<?php

namespace App\Services\Payments;

use App\Models\Contribution;
use App\Models\Organization;
use App\Models\PaymentTransaction;
use App\Services\AuditLogger;
use App\Services\Contributions\ContributionAllocator;
use App\Services\Contributions\ObligationGenerator;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reacts to Safaricom's STK Push callback — the only event in the whole application allowed to
 * turn a PaymentTransaction into a verified Contribution and post it to the ledger (Part 36:
 * never treat the STK Push request itself as a completed payment). Idempotent against Safaricom
 * retrying the same callback: a transaction already out of its initial initiated/pending state
 * is left untouched.
 */
class DarajaCallbackHandler
{
    public function __construct(
        private readonly LedgerPoster $poster,
        private readonly LedgerProvisioner $provisioner,
        private readonly ContributionAllocator $allocator,
        private readonly ObligationGenerator $generator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  The full decoded callback JSON body Safaricom POSTs.
     */
    public function handle(Organization $organization, array $payload): void
    {
        $body = data_get($payload, 'Body.stkCallback');

        if (! is_array($body)) {
            return;
        }

        $checkoutRequestId = data_get($body, 'CheckoutRequestID');

        if (! $checkoutRequestId) {
            return;
        }

        $transaction = PaymentTransaction::where('organization_id', $organization->id)
            ->where('checkout_request_id', $checkoutRequestId)
            ->first();

        if (! $transaction) {
            // Either a callback for a different organization's shortcode, or a transaction we
            // don't recognize. Nothing to do — the controller still acks 200 to Safaricom.
            return;
        }

        DB::transaction(function () use ($transaction, $body, $organization) {
            $transaction = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();

            if (! $transaction || ! in_array($transaction->status, [PaymentTransaction::STATUS_INITIATED, PaymentTransaction::STATUS_PENDING], true)) {
                return;
            }

            $resultCode = (int) data_get($body, 'ResultCode', 1);
            $resultDesc = (string) data_get($body, 'ResultDesc', '');

            $transaction->raw_callback = $body;
            $transaction->result_code = $resultCode;
            $transaction->result_desc = $resultDesc;

            if ($resultCode !== 0) {
                // 1032 = "Request cancelled by user" in Safaricom's catalogue; everything else
                // (e.g. 1037 timeout, 1 insufficient funds) is treated as a failure rather than
                // a deliberate cancellation.
                $transaction->status = $resultCode === 1032
                    ? PaymentTransaction::STATUS_CANCELLED
                    : PaymentTransaction::STATUS_FAILED;
                $transaction->save();

                return;
            }

            $items = collect(data_get($body, 'CallbackMetadata.Item', []))
                ->filter(fn ($item) => is_array($item) && isset($item['Name']))
                ->keyBy('Name');

            $receiptNumber = $items->get('MpesaReceiptNumber')['Value'] ?? null;
            $confirmedAmount = $items->get('Amount')['Value'] ?? null;

            // Daraja's STK Push JSON always carries a whole-shilling amount (enforced on our side
            // at initiation too — see InitiateStkPushRequest), so casting straight to int here is
            // a safe truncation of an already-integral value, not floating-point arithmetic on
            // money (Part 36 forbids the latter, not this). Documented in FINANCIAL_LEDGER.md.
            $confirmedCents = $confirmedAmount !== null ? ((int) $confirmedAmount) * 100 : null;

            if ($confirmedCents !== null && $confirmedCents !== $transaction->amount_cents) {
                $transaction->status = PaymentTransaction::STATUS_FAILED;
                $transaction->result_desc = "Amount mismatch: requested {$transaction->amount_cents} cents, Safaricom confirmed {$confirmedCents} cents. Not posted — contact support.";
                $transaction->save();

                return;
            }

            $account = $transaction->financialAccount;

            if (! $account) {
                $transaction->status = PaymentTransaction::STATUS_FAILED;
                $transaction->result_desc = 'Payment confirmed by Safaricom but no financial account was configured to receive it. Contact support — the money is safe with Safaricom/the chama\'s own till, nothing was lost.';
                $transaction->mpesa_receipt_number = $receiptNumber;
                $transaction->save();

                return;
            }

            $this->provisioner->provisionSystemAccounts($organization);
            $this->provisioner->provisionForFinancialAccount($account);

            if ($transaction->contribution_schedule_id) {
                $this->generator->generate($transaction->schedule, CarbonImmutable::now());
            }

            $contribution = Contribution::create([
                'organization_id' => $organization->id,
                'user_id' => $transaction->user_id,
                'contribution_schedule_id' => $transaction->contribution_schedule_id,
                'amount_cents' => $transaction->amount_cents,
                'currency' => $transaction->currency,
                'payment_method' => Contribution::METHOD_MPESA_STK,
                'transaction_reference' => $receiptNumber,
                'paid_at' => CarbonImmutable::now(),
                'recorded_by' => $transaction->initiated_by,
                'status' => Contribution::STATUS_PENDING_VERIFICATION,
            ]);

            $savingsAccount = $organization->memberSavingsAccount();

            $entry = $this->poster->post(
                $organization,
                "M-Pesa contribution: {$transaction->user->name} (receipt {$receiptNumber})",
                [
                    ['ledger_account_id' => $account->ledgerAccount->id, 'debit_cents' => $contribution->amount_cents],
                    ['ledger_account_id' => $savingsAccount->id, 'credit_cents' => $contribution->amount_cents, 'user_id' => $contribution->user_id],
                ],
                $transaction->initiator,
                source: $contribution,
            );

            // Auto-verified: unlike a manually-recorded payment (which needs a human official to
            // confirm the claim is real — Part 13), Safaricom's own callback *is* the
            // confirmation. verified_by stays null to make "nobody manually verified this" plain
            // in an audit trail, while status/verified_at show it was accepted immediately.
            $contribution->status = Contribution::STATUS_VERIFIED;
            $contribution->verified_by = null;
            $contribution->verified_at = CarbonImmutable::now();
            $contribution->financial_account_id = $account->id;
            $contribution->journal_entry_id = $entry->id;
            $contribution->notes = "Auto-verified via M-Pesa STK Push (receipt {$receiptNumber}).";
            $contribution->save();

            $this->allocator->allocate($contribution);

            $transaction->status = PaymentTransaction::STATUS_COMPLETED;
            $transaction->mpesa_receipt_number = $receiptNumber;
            $transaction->contribution_id = $contribution->id;
            $transaction->save();

            $this->auditLogger->log(
                'contribution.mpesa_stk_confirmed',
                $contribution,
                organizationId: $organization->id,
                userId: $transaction->initiated_by,
                after: $contribution->only(['user_id', 'amount_cents', 'transaction_reference']),
            );
        });
    }
}
