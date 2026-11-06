<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One debit or credit line of a JournalEntry. Exactly one of debit_cents/credit_cents is
 * non-zero (enforced by LedgerPoster, the only code allowed to create these). Never updated or
 * deleted after creation.
 */
class LedgerPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'ledger_account_id',
        'organization_id',
        'user_id',
        'debit_cents',
        'credit_cents',
    ];

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
