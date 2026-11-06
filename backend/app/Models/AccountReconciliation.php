<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point-in-time comparison between a financial account's bank/M-Pesa statement balance and
 * what the ledger says it should be. Purely a record — it never adjusts the ledger itself; a
 * real variance gets investigated and corrected through a proper journal entry (an adjustment or
 * a missed transaction being recorded), not by quietly overwriting a number here.
 */
class AccountReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'financial_account_id',
        'statement_date',
        'statement_balance_cents',
        'computed_balance_cents',
        'variance_cents',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date:Y-m-d',
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
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isBalanced(): bool
    {
        return $this->variance_cents === 0;
    }
}
