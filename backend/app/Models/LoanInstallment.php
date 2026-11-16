<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One expected installment of a disbursed loan — generated once, in full, by
 * LoanInstallmentScheduleGenerator at disbursement time. Its existence is not proof of payment
 * (the same principle Part 11 states for contribution obligations): amount_paid fields are only
 * ever mutated by LoanRepaymentAllocator.
 */
class LoanInstallment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'loan_id',
        'organization_id',
        'installment_number',
        'due_date',
        'principal_due_cents',
        'interest_due_cents',
        'principal_paid_cents',
        'interest_paid_cents',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
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
     * @return HasMany<LoanRepaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class);
    }

    public function totalDueCents(): int
    {
        return $this->principal_due_cents + $this->interest_due_cents;
    }

    public function totalPaidCents(): int
    {
        return $this->principal_paid_cents + $this->interest_paid_cents;
    }

    public function outstandingCents(): int
    {
        return max(0, $this->totalDueCents() - $this->totalPaidCents());
    }

    public function isFullyPaid(): bool
    {
        return $this->totalPaidCents() >= $this->totalDueCents();
    }
}
