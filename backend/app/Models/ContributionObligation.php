<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One expected contribution for one member for one period of a schedule (e.g. "Ken, 21 Oct
 * 2026, KES 100"). Created by ContributionObligationGenerator, never implies payment by its
 * mere existence (Part 11 — "do not record a contribution as paid merely because a scheduled
 * obligation exists").
 */
class ContributionObligation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_WAIVED = 'waived';

    public const PENALTY_NONE = 'none';

    public const PENALTY_ASSESSED = 'assessed';

    public const PENALTY_WAIVED = 'waived';

    public const PENALTY_COLLECTED = 'collected';

    protected $fillable = [
        'contribution_schedule_id',
        'organization_id',
        'user_id',
        'period_start',
        'period_end',
        'due_at',
        'grace_expires_at',
        'amount_due_cents',
        'amount_paid_cents',
        'status',
        'penalty_amount_cents',
        'penalty_status',
        'penalty_waived_by',
        'penalty_waived_reason',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'due_at' => 'datetime',
            'grace_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ContributionSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ContributionSchedule::class, 'contribution_schedule_id');
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
     * @return HasMany<ContributionAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ContributionAllocation::class);
    }

    public function outstandingCents(): int
    {
        return max(0, $this->amount_due_cents - $this->amount_paid_cents);
    }

    public function isFullyPaid(): bool
    {
        return $this->amount_paid_cents >= $this->amount_due_cents;
    }
}
