<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One account in the chama's chart of accounts. Two kinds:
 *  - backed by a FinancialAccount (type 'asset') — cash, bank, M-Pesa.
 *  - "system" accounts with no physical backing (Part 29's accounting domain): the
 *    member-savings liability, penalty/other income, general expenses.
 *
 * Balance is always derived from ledger_postings, never stored — see LedgerPoster.
 */
class LedgerAccount extends Model
{
    use HasFactory;

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    /** Account codes for the fixed system accounts every chama is provisioned with. */
    public const CODE_MEMBER_SAVINGS = 'member_savings';

    public const CODE_PENALTY_INCOME = 'penalty_income';

    public const CODE_GENERAL_EXPENSE = 'general_expense';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'type',
        'is_system',
        'financial_account_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
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
     * @return HasMany<LedgerPosting, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(LedgerPosting::class);
    }

    /**
     * Asset and expense accounts have a normal debit balance (debits increase them); liability
     * and income accounts have a normal credit balance (credits increase them). This is the one
     * piece of real double-entry bookkeeping every balance calculation in this app routes
     * through — never reimplemented ad hoc elsewhere.
     */
    public function balanceCents(?int $forUserId = null): int
    {
        $query = $this->postings();

        if ($forUserId !== null) {
            $query->where('user_id', $forUserId);
        }

        $totals = $query->selectRaw('COALESCE(SUM(debit_cents), 0) as debits, COALESCE(SUM(credit_cents), 0) as credits')->first();

        $debits = (int) $totals->debits;
        $credits = (int) $totals->credits;

        return match ($this->type) {
            self::TYPE_ASSET, self::TYPE_EXPENSE => $debits - $credits,
            self::TYPE_LIABILITY, self::TYPE_INCOME => $credits - $debits,
            default => $debits - $credits,
        };
    }
}
