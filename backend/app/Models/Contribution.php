<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recorded payment (Part 13). Starts life unverified — its existence is not proof of a
 * successful payment. Only a verified contribution is allocated against obligations and counted
 * in any member/chama total.
 */
class Contribution extends Model
{
    use HasFactory;

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK = 'bank';

    public const METHOD_MANUAL_MPESA = 'manual_mpesa';

    public const METHOD_MPESA_STK = 'mpesa_stk';

    public const METHOD_OTHER = 'other';

    /**
     * Methods a member/official can pick when manually recording a payment. METHOD_MPESA_STK is
     * deliberately excluded — it is only ever set by DarajaCallbackHandler on a confirmed STK
     * Push callback, never chosen from the manual-recording form (Part 36: never treat an STK
     * Push request as a completed payment; only Safaricom's callback can mark one that way).
     */
    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_BANK,
        self::METHOD_MANUAL_MPESA,
        self::METHOD_OTHER,
    ];

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'user_id',
        'contribution_schedule_id',
        'financial_account_id',
        'journal_entry_id',
        'amount_cents',
        'currency',
        'payment_method',
        'transaction_reference',
        'paid_at',
        'recorded_by',
        'status',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<ContributionSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ContributionSchedule::class, 'contribution_schedule_id');
    }

    /**
     * @return HasMany<ContributionAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ContributionAllocation::class);
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function paymentMethodLabel(): string
    {
        return $this->payment_method === self::METHOD_MPESA_STK
            ? 'M-Pesa'
            : ucfirst(str_replace('_', ' ', $this->payment_method));
    }
}
