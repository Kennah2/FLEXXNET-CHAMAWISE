<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One payment a borrower made against their loan, immediately allocated across outstanding
 * installments (oldest first) and posted to the ledger — unlike a manually-recorded
 * contribution, a loan repayment needs no separate verification step (Part 13's verification
 * requirement is specific to contributions; a repayment is always recorded by an actor who
 * already holds the appropriate permission, same as an expense).
 */
class LoanRepayment extends Model
{
    use HasFactory;

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'loan_id',
        'organization_id',
        'amount_cents',
        'principal_cents',
        'interest_cents',
        'paid_at',
        'financial_account_id',
        'recorded_by',
        'status',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return HasMany<LoanRepaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class);
    }
}
