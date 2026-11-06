<?php

namespace Tests\Feature\Payments;

use App\Models\FinancialAccount;
use App\Models\PaymentGatewayCredential;
use App\Models\PaymentTransaction;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class StkPushInitiationTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function configuredChama(string $name): array
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

        return [$ken, $organization, $account, $credential];
    }

    private function fakeOauth(): void
    {
        Http::fake([
            'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'fake-token', 'expires_in' => '3599']),
        ]);
    }

    public function test_a_member_can_initiate_an_stk_push_for_themselves(): void
    {
        [$ken, $organization] = $this->configuredChama('STK Chama');

        Http::fake([
            'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'fake-token']),
            'sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'MerchantRequestID' => 'merchant-1',
                'CheckoutRequestID' => 'checkout-1',
            ]),
        ]);

        $response = $this->actingAs($ken)->postJson(route('organizations.contributions.mpesa.initiate', $organization), [
            'phone' => '0712345678',
            'amount' => '100',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['status' => PaymentTransaction::STATUS_PENDING]);

        $transaction = PaymentTransaction::firstWhere('organization_id', $organization->id);
        $this->assertNotNull($transaction);
        $this->assertSame('254712345678', $transaction->phone_number);
        $this->assertSame(10000, $transaction->amount_cents);
        $this->assertSame('checkout-1', $transaction->checkout_request_id);
        $this->assertSame(PaymentTransaction::STATUS_PENDING, $transaction->status);
    }

    public function test_daraja_rejecting_the_request_marks_the_transaction_failed(): void
    {
        [$ken, $organization] = $this->configuredChama('Rejected STK Chama');

        Http::fake([
            'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'fake-token']),
            'sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '1',
                'ResponseDescription' => 'Invalid Access Token',
            ]),
        ]);

        $response = $this->actingAs($ken)->postJson(route('organizations.contributions.mpesa.initiate', $organization), [
            'phone' => '0712345678',
            'amount' => '100',
        ]);

        $response->assertStatus(422);

        $transaction = PaymentTransaction::firstWhere('organization_id', $organization->id);
        $this->assertSame(PaymentTransaction::STATUS_FAILED, $transaction->status);
        $this->assertSame('Invalid Access Token', $transaction->result_desc);
    }

    public function test_a_transport_failure_reaching_daraja_fails_gracefully_instead_of_a_500(): void
    {
        // Regression test: DarajaClient originally let a transport-level exception (DNS failure,
        // connection refused, a network policy rejecting the host — exactly what this sandboxed
        // development container does, since it has no route to Safaricom) bubble up uncaught
        // instead of becoming a DarajaRequestException, which would have surfaced as an
        // unhandled 500 rather than the friendly "pay manually instead" message.
        [$ken, $organization] = $this->configuredChama('Unreachable STK Chama');

        Http::fake(function () {
            throw new ConnectionException('Could not resolve host: sandbox.safaricom.co.ke');
        });

        $response = $this->actingAs($ken)->postJson(route('organizations.contributions.mpesa.initiate', $organization), [
            'phone' => '0712345678',
            'amount' => '100',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Could not reach Safaricom: Could not resolve host: sandbox.safaricom.co.ke']);

        $transaction = PaymentTransaction::firstWhere('organization_id', $organization->id);
        $this->assertSame(PaymentTransaction::STATUS_FAILED, $transaction->status);
    }

    public function test_initiating_without_daraja_configured_fails_gracefully_with_no_transaction_created(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Unconfigured STK Chama');

        $response = $this->actingAs($ken)->postJson(route('organizations.contributions.mpesa.initiate', $organization), [
            'phone' => '0712345678',
            'amount' => '100',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, PaymentTransaction::where('organization_id', $organization->id)->count());
    }

    public function test_a_member_cannot_initiate_an_stk_push_on_behalf_of_another_member(): void
    {
        [$ken, $organization] = $this->configuredChama('No Proxy STK Chama');
        $frank = $this->addMemberWithRole($organization, 'member');

        $this->fakeOauth();

        $response = $this->actingAs($frank)->postJson(route('organizations.contributions.mpesa.initiate', $organization), [
            'user_id' => $ken->id,
            'phone' => '0712345678',
            'amount' => '100',
        ]);

        $response->assertForbidden();
    }

    public function test_fractional_shilling_amounts_are_rejected(): void
    {
        [$ken, $organization] = $this->configuredChama('Fractional STK Chama');

        $response = $this->actingAs($ken)->postJson(route('organizations.contributions.mpesa.initiate', $organization), [
            'phone' => '0712345678',
            'amount' => '100.50',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, PaymentTransaction::where('organization_id', $organization->id)->count());
    }

    public static function phoneFormatProvider(): array
    {
        return [
            'leading zero' => ['0712345678', '254712345678'],
            'already international' => ['254712345678', '254712345678'],
            'plus international' => ['+254712345678', '254712345678'],
            'bare nine digits' => ['712345678', '254712345678'],
            'safaricom 01 prefix' => ['0112345678', '254112345678'],
        ];
    }

    #[DataProvider('phoneFormatProvider')]
    public function test_phone_numbers_are_normalized_to_the_international_format(string $input, string $expected): void
    {
        [$ken, $organization] = $this->configuredChama('Phone Format Chama '.$expected);

        Http::fake([
            'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'fake-token']),
            'sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
                'MerchantRequestID' => 'merchant-x',
                'CheckoutRequestID' => 'checkout-x',
            ]),
        ]);

        $this->actingAs($ken)->postJson(route('organizations.contributions.mpesa.initiate', $organization), [
            'phone' => $input,
            'amount' => '100',
        ]);

        $transaction = PaymentTransaction::where('organization_id', $organization->id)->first();
        $this->assertSame($expected, $transaction->phone_number);
    }
}
