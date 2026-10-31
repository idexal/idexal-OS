<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, SoftDeletes;

    public const ROLE_USER = 'user';

    public const ROLE_DEVELOPER = 'developer';

    public const ROLE_ADMIN = 'admin';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'locale',
        'timezone',
        'phone',
        'job_title',
        'email_verified_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isDeveloper(): bool
    {
        return in_array($this->role, [self::ROLE_DEVELOPER, self::ROLE_ADMIN], true);
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    public function roleLabel(): string
    {
        return __('ui.roles.'.$this->role);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Organization, $this>
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return HasMany<DesktopSession, $this>
     */
    public function desktopSessions(): HasMany
    {
        return $this->hasMany(DesktopSession::class);
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function membershipFor(Organization|string $organization): ?Membership
    {
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;

        return $this->memberships()
            ->where('organization_id', $organizationId)
            ->first();
    }

    public function belongsToOrganization(Organization|string $organization): bool
    {
        return $this->membershipFor($organization)?->isActive() ?? false;
    }
}
