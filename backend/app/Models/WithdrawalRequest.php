<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member's request to withdraw from their own savings (Part 14). Approval and payment are
 * deliberately separate steps/permissions — approval authorizes it, payment is a distinct act
 * that actually moves money and posts the ledger entry.
 */
class WithdrawalRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'user_id',
        'requested_by',
        'financial_account_id',
        'amount_cents',
        'reason',
        'required_approvals',
        'status',
        'paid_by',
        'paid_at',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
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
     * The member whose savings this withdraws from.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
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

    /**
     * @return HasMany<WithdrawalApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(WithdrawalApproval::class);
    }

    public function approvalsCount(): int
    {
        return $this->approvals()->where('decision', WithdrawalApproval::DECISION_APPROVED)->count();
    }

    public function isFullyApproved(): bool
    {
        return $this->approvalsCount() >= $this->required_approvals;
    }
}
