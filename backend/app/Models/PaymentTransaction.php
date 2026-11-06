<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to collect a contribution via STK Push, from the moment it's initiated through to
 * Safaricom's final callback. Its existence, or even a "pending" status, is never treated as a
 * completed payment anywhere in the app (Part 36) — only a callback-driven transition to
 * STATUS_COMPLETED causes DarajaCallbackHandler to create a Contribution and post the ledger.
 */
class PaymentTransaction extends Model
{
    public const PROVIDER_DARAJA = 'daraja';

    public const STATUS_INITIATED = 'initiated';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'user_id',
        'initiated_by',
        'contribution_schedule_id',
        'contribution_id',
        'financial_account_id',
        'phone_number',
        'amount_cents',
        'currency',
        'provider',
        'merchant_request_id',
        'checkout_request_id',
        'status',
        'result_code',
        'result_desc',
        'mpesa_receipt_number',
        'raw_callback',
    ];

    protected function casts(): array
    {
        return [
            'raw_callback' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * @return BelongsTo<ContributionSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ContributionSchedule::class, 'contribution_schedule_id');
    }

    /**
     * @return BelongsTo<Contribution, $this>
     */
    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }
}
