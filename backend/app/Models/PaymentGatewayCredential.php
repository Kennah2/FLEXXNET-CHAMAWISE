<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One chama's own Safaricom Daraja app credentials (Part 28's custody limitation: CHAMA WISE
 * never pools member funds through a shared platform paybill — every chama collects into its
 * own Safaricom shortcode, and this app only initiates the STK Push request and records the
 * result). consumer_key/consumer_secret/passkey are encrypted at rest via Model casts; nothing
 * here is ever logged or rendered back to the browser once saved (see SECURITY.md).
 */
class PaymentGatewayCredential extends Model
{
    public const PROVIDER_DARAJA = 'daraja';

    public const ENV_SANDBOX = 'sandbox';

    public const ENV_PRODUCTION = 'production';

    protected $fillable = [
        'organization_id',
        'provider',
        'environment',
        'consumer_key',
        'consumer_secret',
        'shortcode',
        'passkey',
        'financial_account_id',
        'callback_token',
        'is_enabled',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'consumer_key',
        'consumer_secret',
        'passkey',
        'callback_token',
    ];

    protected function casts(): array
    {
        return [
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'passkey' => 'encrypted',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function baseUrl(): string
    {
        return $this->environment === self::ENV_PRODUCTION
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->consumer_key && $this->consumer_secret && $this->shortcode && $this->passkey && $this->financial_account_id);
    }
}
