<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use HasUlids;

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_DEVELOPER = 'developer';

    public const ROLE_MEMBER = 'member';

    public const ROLE_BILLING = 'billing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INVITED = 'invited';

    protected $table = 'organization_user';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'status',
        'invited_by',
        'joined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function assignableRoles(): array
    {
        return [
            self::ROLE_OWNER => __('ui.roles.owner'),
            self::ROLE_ADMIN => __('ui.roles.admin'),
            self::ROLE_DEVELOPER => __('ui.roles.developer'),
            self::ROLE_MEMBER => __('ui.roles.member'),
            self::ROLE_BILLING => __('ui.roles.billing'),
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function canManageMembers(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function canManageBilling(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_BILLING], true);
    }

    public function canManageApiKeys(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_DEVELOPER], true);
    }

    public function roleLabel(): string
    {
        return __('ui.roles.'.$this->role);
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
