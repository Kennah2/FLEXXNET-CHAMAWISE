<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The auditable link between a verified payment and the obligation(s) it pays down. A reversal
 * never deletes this row — it stamps reversed_at instead, keeping the full history intact
 * (Part 12 — never silently overwrite or delete posted financial history).
 */
class ContributionAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'contribution_id',
        'contribution_obligation_id',
        'amount_cents',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contribution, $this>
     */
    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    /**
     * @return BelongsTo<ContributionObligation, $this>
     */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(ContributionObligation::class, 'contribution_obligation_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
