<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member's loan, from application through to fully repaid (or written off). Approval sets the
 * actual terms (amount/rate/term) — which may differ from what was requested, since a real chama
 * committee routinely adjusts these rather than only ever granting exactly what was asked for
 * (Part 36: never hardcode a chama's lending rules). Disbursement and repayment are separate,
 * deliberate acts that post the ledger, mirroring how withdrawals separate approval from payment.
 */
class Loan extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DISBURSED = 'disbursed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'user_id',
        'requested_by',
        'requested_amount_cents',
        'requested_term_months',
        'reason',
        'status',
        'approved_amount_cents',
        'term_months',
        'interest_rate_bps',
        'approved_by',
        'approved_at',
        'financial_account_id',
        'disbursed_by',
        'disbursed_at',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
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
     * The borrower.
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
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
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
     * @return HasMany<LoanInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    /**
     * @return HasMany<LoanRepayment, $this>
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function outstandingPrincipalCents(): int
    {
        return $this->installments->sum(fn (LoanInstallment $i) => max(0, $i->principal_due_cents - $i->principal_paid_cents));
    }

    public function outstandingInterestCents(): int
    {
        return $this->installments->sum(fn (LoanInstallment $i) => max(0, $i->interest_due_cents - $i->interest_paid_cents));
    }

    public function isFullyRepaid(): bool
    {
        return $this->installments->isNotEmpty() && $this->installments->every(
            fn (LoanInstallment $i) => $i->principal_paid_cents >= $i->principal_due_cents && $i->interest_paid_cents >= $i->interest_due_cents
        );
    }
}
