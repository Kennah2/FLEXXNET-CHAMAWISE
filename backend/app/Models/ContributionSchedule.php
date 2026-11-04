<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ContributionSchedule extends Model
{
    use HasFactory;

    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_CUSTOM = 'custom';

    public const FREQUENCIES = [
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_CUSTOM,
    ];

    public const APPLIES_TO_ALL = 'all';

    public const APPLIES_TO_SELECTED = 'selected';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'frequency',
        'currency',
        'amount_cents',
        'start_date',
        'end_date',
        'deadline_time',
        'timezone',
        'grace_period_days',
        'penalty_amount_cents',
        'applies_to',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
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
     * @return HasMany<ContributionObligation, $this>
     */
    public function obligations(): HasMany
    {
        return $this->hasMany(ContributionObligation::class);
    }

    /**
     * Explicitly selected members when applies_to = 'selected'. Irrelevant when applies_to = 'all'.
     *
     * @return BelongsToMany<User, $this>
     */
    public function selectedMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'schedule_members');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The organization members this schedule actually applies to: everyone active, or only the
     * explicitly selected ones (Part 11 — "schedules that apply to all members and schedules
     * that apply to selected members").
     *
     * @return Collection<int, User>
     */
    public function applicableMembers(): Collection
    {
        if ($this->applies_to === self::APPLIES_TO_SELECTED) {
            return $this->selectedMembers()->get();
        }

        return $this->organization->members()->get();
    }
}
