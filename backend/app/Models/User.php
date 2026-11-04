<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'is_platform_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Every chama this user has (or had) a membership record in, regardless of status.
     *
     * @return HasMany<ChamaMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ChamaMembership::class);
    }

    /**
     * Chamas this user currently has an active membership in.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'chama_memberships')
            ->withPivot(['membership_number', 'status', 'joined_at', 'left_at'])
            ->wherePivot('status', ChamaMembership::STATUS_ACTIVE)
            ->withTimestamps();
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /**
     * Which column a login identifier should be matched against: 'email' if it looks like one,
     * 'username' otherwise. Used by LoginRequest so a member can sign in with either.
     */
    public static function loginFieldFor(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    }

    /**
     * Usernames are always stored and matched lowercased — not left to the database's collation,
     * which is case-insensitive by default on MySQL but case-sensitive on SQLite. Normalizing in
     * PHP keeps login behavior identical regardless of which database the app runs against.
     */
    public static function normalizeUsername(string $username): string
    {
        return Str::of($username)->trim()->lower()->value();
    }
}
