<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A real-world place the chama's money sits (cash box, bank account, M-Pesa till). Every active
 * financial account has exactly one backing `ledger_account` of type 'asset' (provisioned by
 * LedgerProvisioner), which is what the double-entry ledger actually posts against — this model
 * is the human-facing record; the ledger account is the accounting one.
 */
class FinancialAccount extends Model
{
    use HasFactory;

    public const TYPE_CASH = 'cash';

    public const TYPE_BANK = 'bank';

    public const TYPE_MOBILE_MONEY = 'mobile_money';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_CASH,
        self::TYPE_BANK,
        self::TYPE_MOBILE_MONEY,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'type',
        'account_number',
        'currency',
        'opening_balance_cents',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
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
     * @return HasOne<LedgerAccount, $this>
     */
    public function ledgerAccount(): HasOne
    {
        return $this->hasOne(LedgerAccount::class);
    }

    /**
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * @return HasMany<AccountReconciliation, $this>
     */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(AccountReconciliation::class);
    }

    /**
     * The account's current balance: opening balance plus every posting ever made to its
     * backing ledger account. Always computed from the ledger, never stored — a cached running
     * total is how balances silently drift from reality.
     */
    public function balanceCents(): int
    {
        $ledgerAccount = $this->ledgerAccount;

        if (! $ledgerAccount) {
            return $this->opening_balance_cents;
        }

        return $this->opening_balance_cents + $ledgerAccount->balanceCents();
    }
}
