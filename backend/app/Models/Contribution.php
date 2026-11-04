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

    public const METHOD_OTHER = 'other';

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

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }
}
