<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A chama expense (Part 12): posted straight to the ledger on creation (Debit expense account,
 * Credit the paying financial account) — unlike a withdrawal, an expense doesn't need a
 * multi-approval workflow in this phase, just the manage-finances permission. Still reversible,
 * never deletable.
 */
class Expense extends Model
{
    use HasFactory;

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'financial_account_id',
        'ledger_account_id',
        'amount_cents',
        'description',
        'expense_date',
        'recorded_by',
        'status',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date:Y-m-d',
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

    /**
     * @return BelongsTo<LedgerAccount, $this>
     */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
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
}
