<?php

namespace Tests\Feature\Payments;

use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\PaymentGatewayCredential;
use App\Models\User;
use App\Services\Ledger\LedgerProvisioner;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class DarajaCredentialControllerTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function financialAccount(Organization $organization, User $creator): FinancialAccount
    {
        $provisioner = app(LedgerProvisioner::class);
        $provisioner->provisionSystemAccounts($organization);

        $account = FinancialAccount::create([
            'organization_id' => $organization->id,
            'name' => 'M-Pesa Till',
            'type' => FinancialAccount::TYPE_MOBILE_MONEY,
            'currency' => $organization->currency,
            'opening_balance_cents' => 0,
            'is_default' => true,
            'created_by' => $creator->id,
        ]);
        $provisioner->provisionForFinancialAccount($account);

        return $account;
    }

    public function test_chairperson_can_save_and_enable_daraja_credentials(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Daraja Chama');
        $account = $this->financialAccount($organization, $ken);

        $response = $this->actingAs($ken)->put(route('organizations.payments.daraja.update', $organization), [
            'environment' => 'sandbox',
            'shortcode' => '174379',
            'consumer_key' => 'test-consumer-key',
            'consumer_secret' => 'test-consumer-secret',
            'passkey' => 'test-passkey',
            'financial_account_id' => $account->id,
            'is_enabled' => '1',
        ]);

        $response->assertRedirect(route('organizations.payments.daraja.edit', $organization));

        $credential = PaymentGatewayCredential::where('organization_id', $organization->id)->first();
        $this->assertNotNull($credential);
        $this->assertTrue($credential->is_enabled);
        $this->assertSame('174379', $credential->shortcode);
        $this->assertSame('test-consumer-key', $credential->consumer_key);
        $this->assertSame('test-consumer-secret', $credential->consumer_secret);
        $this->assertSame($account->id, $credential->financial_account_id);
        $this->assertNotEmpty($credential->callback_token);

        $raw = DB::table('payment_gateway_credentials')->where('id', $credential->id)->first();
        $this->assertNotSame('test-consumer-secret', $raw->consumer_secret);
        $this->assertNotSame('test-passkey', $raw->passkey);
        $this->assertStringNotContainsString('test-consumer-secret', $raw->consumer_secret);
    }

    public function test_enabling_without_all_required_fields_is_silently_not_enabled(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Incomplete Daraja Chama');
        $account = $this->financialAccount($organization, $ken);

        $response = $this->actingAs($ken)->put(route('organizations.payments.daraja.update', $organization), [
            'environment' => 'sandbox',
            'shortcode' => '174379',
            // No consumer_key/consumer_secret/passkey provided.
            'financial_account_id' => $account->id,
            'is_enabled' => '1',
        ]);

        $response->assertRedirect();
        $credential = PaymentGatewayCredential::where('organization_id', $organization->id)->first();
        $this->assertNotNull($credential);
        $this->assertFalse($credential->is_enabled);
    }

    public function test_blank_secret_fields_on_update_keep_the_previously_saved_values(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Keep Secrets Chama');
        $account = $this->financialAccount($organization, $ken);

        $this->actingAs($ken)->put(route('organizations.payments.daraja.update', $organization), [
            'environment' => 'sandbox',
            'shortcode' => '174379',
            'consumer_key' => 'original-key',
            'consumer_secret' => 'original-secret',
            'passkey' => 'original-passkey',
            'financial_account_id' => $account->id,
        ]);

        $this->actingAs($ken)->put(route('organizations.payments.daraja.update', $organization), [
            'environment' => 'production',
            'shortcode' => '174379',
            'financial_account_id' => $account->id,
        ]);

        $credential = PaymentGatewayCredential::where('organization_id', $organization->id)->first();
        $this->assertSame('production', $credential->environment);
        $this->assertSame('original-key', $credential->consumer_key);
        $this->assertSame('original-secret', $credential->consumer_secret);
        $this->assertSame('original-passkey', $credential->passkey);
    }

    public function test_an_ordinary_member_cannot_view_or_update_daraja_settings(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('No Access Daraja Chama');
        $account = $this->financialAccount($organization, $ken);
        $member = $this->addMemberWithRole($organization, 'member');

        $this->actingAs($member)->get(route('organizations.payments.daraja.edit', $organization))->assertForbidden();

        $this->actingAs($member)->put(route('organizations.payments.daraja.update', $organization), [
            'environment' => 'sandbox',
            'shortcode' => '174379',
            'financial_account_id' => $account->id,
        ])->assertForbidden();
    }

    public function test_a_member_of_another_chama_cannot_update_this_chamas_daraja_settings(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Daraja Chama A');
        $accountA = $this->financialAccount($organizationA, $ken);
        [, $organizationB] = $this->createChamaWithChairperson('Daraja Chama B');
        $chairOfB = $this->addMemberWithRole($organizationB, 'chairperson');

        $this->actingAs($chairOfB)->put(route('organizations.payments.daraja.update', $organizationA), [
            'environment' => 'sandbox',
            'shortcode' => '174379',
            'financial_account_id' => $accountA->id,
        ])->assertForbidden();
    }
}
