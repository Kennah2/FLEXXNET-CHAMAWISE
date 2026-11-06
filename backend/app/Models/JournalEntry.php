<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The header of one balanced double-entry posting (Part 12/29). Never edited or deleted once
 * created — a mistake is corrected by posting a second, reversing entry linked via
 * reverses_journal_entry_id (see LedgerPoster::reverse()).
 */
class JournalEntry extends Model
{
    use HasFactory;

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'description',
        'source_type',
        'source_id',
        'posted_at',
        'created_by',
        'status',
        'reverses_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<LedgerPosting, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(LedgerPosting::class);
    }

    /**
     * The business record this entry was posted for (a Contribution, WithdrawalRequest,
     * Expense, ...).
     */
    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_entry_id');
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }
}
