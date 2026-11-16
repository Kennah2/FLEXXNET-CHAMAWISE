<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The auditable link between one loan repayment and one installment it paid down (fully or
 * partially) — the loan equivalent of ContributionAllocation. A reversal stamps reversed_at
 * rather than deleting the row (Part 12).
 */
class LoanRepaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_repayment_id',
        'loan_installment_id',
        'principal_cents',
        'interest_cents',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LoanRepayment, $this>
     */
    public function repayment(): BelongsTo
    {
        return $this->belongsTo(LoanRepayment::class, 'loan_repayment_id');
    }

    /**
     * @return BelongsTo<LoanInstallment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
