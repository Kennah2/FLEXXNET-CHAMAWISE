<?php

namespace Tests\Feature\Payments;

use App\Models\Contribution;
use App\Models\FinancialAccount;
use App\Models\LedgerAccount;
use App\Models\PaymentGatewayCredential;
use App\Models\PaymentTransaction;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class DarajaCallbackHandlerTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function pendingTransaction(string $name = 'Callback Chama'): array
    {
        [$ken, $organization] = $this->createChamaWithChairperson($name);

        $provisioner = app(LedgerProvisioner::class);
        $provisioner->provisionSystemAccounts($organization);
        $account = FinancialAccount::create([
            'organization_id' => $organization->id,
            'name' => 'M-Pesa Till',
            'type' => FinancialAccount::TYPE_MOBILE_MONEY,
            'currency' => $organization->currency,
            'opening_balance_cents' => 0,
            'is_default' => true,
            'created_by' => $ken->id,
        ]);
        $provisioner->provisionForFinancialAccount($account);

        $credential = PaymentGatewayCredential::create([
            'organization_id' => $organization->id,
            'provider' => PaymentGatewayCredential::PROVIDER_DARAJA,
            'environment' => PaymentGatewayCredential::ENV_SANDBOX,
            'consumer_key' => 'key',
            'consumer_secret' => 'secret',
            'shortcode' => '174379',
            'passkey' => 'passkey',
            'financial_account_id' => $account->id,
            'callback_token' => 'test-callback-token',
            'is_enabled' => true,
            'created_by' => $ken->id,
        ]);

        $transaction = PaymentTransaction::create([
            'organization_id' => $organization->id,
            'user_id' => $ken->id,
            'initiated_by' => $ken->id,
            'financial_account_id' => $account->id,
            'phone_number' => '254712345678',
            'amount_cents' => 10000,
            'currency' => $organization->currency,
            'provider' => PaymentTransaction::PROVIDER_DARAJA,
            'status' => PaymentTransaction::STATUS_PENDING,
            'merchant_request_id' => 'merchant-1',
            'checkout_request_id' => 'checkout-1',
        ]);

        return [$ken, $organization, $account, $credential, $transaction];
    }

    private function successPayload(string $checkoutRequestId, int $amountShillings = 100, string $receipt = 'QGR12345XY'): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'merchant-1',
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => $amountShillings],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                            ['Name' => 'TransactionDate', 'Value' => 20261106061300],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function failurePayload(string $checkoutRequestId, int $resultCode, string $desc): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'merchant-1',
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => $resultCode,
                    'ResultDesc' => $desc,
                ],
            ],
        ];
    }

    public function test_a_successful_callback_creates_a_verified_contribution_and_posts_the_ledger(): void
    {
        [$ken, $organization, $account, $credential, $transaction] = $this->pendingTransaction();

        $response = $this->postJson(
            route('api.mpesa.callback', ['organization' => $organization->id, 'token' => $credential->callback_token]),
            $this->successPayload('checkout-1'),
        );

        $response->assertOk();
        $response->assertJson(['ResultCode' => 0]);

        $transaction->refresh();
        $this->assertSame(PaymentTransaction::STATUS_COMPLETED, $transaction->status);
        $this->assertSame('QGR12345XY', $transaction->mpesa_receipt_number);
        $this->assertNotNull($transaction->contribution_id);

        $contribution = Contribution::find($transaction->contribution_id);
        $this->assertSame(Contribution::STATUS_VERIFIED, $contribution->status);
        $this->assertSame(Contribution::METHOD_MPESA_STK, $contribution->payment_method);
        $this->assertNull($contribution->verified_by);
        $this->assertSame('QGR12345XY', $contribution->transaction_reference);
        $this->assertNotNull($contribution->journal_entry_id);

        $this->assertSame(10000, $account->fresh()->balanceCents());

        $savings = LedgerAccount::where('organization_id', $organization->id)->where('code', LedgerAccount::CODE_MEMBER_SAVINGS)->first();
        $this->assertSame(10000, $savings->balanceCents($ken->id));
    }

    public function test_a_cancelled_callback_does_not_create_a_contribution(): void
    {
        [, $organization, , $credential, $transaction] = $this->pendingTransaction();

        $this->postJson(
            route('api.mpesa.callback', ['organization' => $organization->id, 'token' => $credential->callback_token]),
            $this->failurePayload('checkout-1', 1032, 'Request cancelled by user'),
        )->assertOk();

        $transaction->refresh();
        $this->assertSame(PaymentTransaction::STATUS_CANCELLED, $transaction->status);
        $this->assertNull($transaction->contribution_id);
        $this->assertSame(0, Contribution::where('organization_id', $organization->id)->count());
    }

    public function test_a_timeout_callback_marks_the_transaction_failed(): void
    {
        [, $organization, , $credential, $transaction] = $this->pendingTransaction();

        $this->postJson(
            route('api.mpesa.callback', ['organization' => $organization->id, 'token' => $credential->callback_token]),
            $this->failurePayload('checkout-1', 1037, 'Timeout in completing transaction'),
        )->assertOk();

        $transaction->refresh();
        $this->assertSame(PaymentTransaction::STATUS_FAILED, $transaction->status);
        $this->assertSame(0, Contribution::where('organization_id', $organization->id)->count());
    }

    public function test_a_duplicate_callback_delivery_is_processed_only_once(): void
    {
        [, $organization, $account, $credential, $transaction] = $this->pendingTransaction();

        $url = route('api.mpesa.callback', ['organization' => $organization->id, 'token' => $credential->callback_token]);
        $payload = $this->successPayload('checkout-1');

        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();

        $this->assertSame(1, Contribution::where('organization_id', $organization->id)->count());
        $this->assertSame(10000, $account->fresh()->balanceCents());
    }

    public function test_a_callback_with_the_wrong_token_is_rejected_and_not_processed(): void
    {
        [, $organization, , , $transaction] = $this->pendingTransaction();

        $this->postJson(
            route('api.mpesa.callback', ['organization' => $organization->id, 'token' => 'wrong-token']),
            $this->successPayload('checkout-1'),
        )->assertOk();

        $transaction->refresh();
        $this->assertSame(PaymentTransaction::STATUS_PENDING, $transaction->status);
    }

    public function test_an_amount_mismatch_fails_the_transaction_instead_of_posting_the_wrong_figure(): void
    {
        [, $organization, , $credential, $transaction] = $this->pendingTransaction();

        $this->postJson(
            route('api.mpesa.callback', ['organization' => $organization->id, 'token' => $credential->callback_token]),
            $this->successPayload('checkout-1', amountShillings: 999),
        )->assertOk();

        $transaction->refresh();
        $this->assertSame(PaymentTransaction::STATUS_FAILED, $transaction->status);
        $this->assertStringContainsString('Amount mismatch', $transaction->result_desc);
        $this->assertSame(0, Contribution::where('organization_id', $organization->id)->count());
    }

    public function test_a_callback_for_an_unknown_checkout_request_id_is_acknowledged_but_ignored(): void
    {
        [, $organization, , $credential] = $this->pendingTransaction();

        $response = $this->postJson(
            route('api.mpesa.callback', ['organization' => $organization->id, 'token' => $credential->callback_token]),
            $this->successPayload('checkout-does-not-exist'),
        );

        $response->assertOk();
        $this->assertSame(0, Contribution::where('organization_id', $organization->id)->count());
    }
}
