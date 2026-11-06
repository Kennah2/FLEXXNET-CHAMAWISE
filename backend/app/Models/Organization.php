<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const GROUP_TYPES = [
        'savings',
        'merry_go_round',
        'table_banking',
        'investment',
        'welfare',
        'other',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'group_type',
        'currency',
        'formation_date',
        'contact_email',
        'contact_phone',
        'location',
        'status',
        'created_by',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'formation_date' => 'date',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $organization) {
            if (blank($organization->slug)) {
                $organization->slug = static::uniqueSlugFor($organization->name);
            }
        });
    }

    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'chama';
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ChamaMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ChamaMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', ChamaMembership::STATUS_ACTIVE);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chama_memberships')
            ->withPivot(['membership_number', 'status', 'joined_at', 'left_at'])
            ->wherePivot('status', ChamaMembership::STATUS_ACTIVE)
            ->withTimestamps();
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<ContributionSchedule, $this>
     */
    public function contributionSchedules(): HasMany
    {
        return $this->hasMany(ContributionSchedule::class);
    }

    /**
     * @return HasMany<ContributionObligation, $this>
     */
    public function contributionObligations(): HasMany
    {
        return $this->hasMany(ContributionObligation::class);
    }

    /**
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * @return HasMany<FinancialAccount, $this>
     */
    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    /**
     * @return HasMany<LedgerAccount, $this>
     */
    public function ledgerAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class);
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * @return HasMany<WithdrawalRequest, $this>
     */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * The chama's member-savings control (liability) ledger account — the sum of every
     * member's sub-ledger balance. Provisioned by LedgerProvisioner when the chama is created.
     */
    public function memberSavingsAccount(): ?LedgerAccount
    {
        return $this->ledgerAccounts()->where('code', LedgerAccount::CODE_MEMBER_SAVINGS)->first();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * A configured default from the chama's settings JSON (Part 8/11): contribution
     * frequency/amount/deadline/grace period/penalty defaults, etc. Never hardcoded —
     * each chama configures its own via the onboarding wizard (Phase 2).
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
