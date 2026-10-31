<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person inviting another. A referral starts pending; it becomes qualified
 * when the invited account pays for something, and rewarded once the referrer
 * has actually been credited.
 */
class Referral extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_REWARDED = 'rewarded';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'referrer_user_id',
        'referrer_organization_id',
        'referred_user_id',
        'referred_organization_id',
        'code',
        'status',
        'reward_amount',
        'reward_currency',
        'qualified_at',
        'rewarded_at',
        'rejected_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reward_amount' => 'decimal:6',
            'qualified_at' => 'datetime',
            'rewarded_at' => 'datetime',
            'rejected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRewarded(): bool
    {
        return $this->status === self::STATUS_REWARDED;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function referrerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'referrer_organization_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function referredOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'referred_organization_id');
    }
}
