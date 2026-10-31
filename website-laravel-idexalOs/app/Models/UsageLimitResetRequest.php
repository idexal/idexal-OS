<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's ask for more spend in one usage period.
 *
 * The member's own open ask is what stops the desktop offering the same request
 * twice, and an approved row is what raises the allowance the desktop reads for
 * that period.
 */
class UsageLimitResetRequest extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'bucket_id',
        'period',
        'reason',
        'status',
        'extension_micro_usd',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extension_micro_usd' => 'integer',
            'reviewed_at' => 'datetime',
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
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Every request for one organization's bucket and period, newest first.
     *
     * @return Collection<int, self>
     */
    public static function forBucket(string $organizationId, string $bucketId, string $period)
    {
        return self::query()
            ->where('organization_id', $organizationId)
            ->where('bucket_id', $bucketId)
            ->where('period', $period)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * The extension granted for a bucket in a period: the sum of what operators
     * actually approved. A pending or denied ask grants nothing.
     */
    public static function extensionMicroUsd(string $organizationId, string $bucketId, string $period): int
    {
        return (int) self::query()
            ->where('organization_id', $organizationId)
            ->where('bucket_id', $bucketId)
            ->where('period', $period)
            ->where('status', self::STATUS_APPROVED)
            ->sum('extension_micro_usd');
    }

    /**
     * The member's own open ask, if they have one.
     */
    public static function openFor(string $organizationId, string $bucketId, string $userId): ?self
    {
        return self::query()
            ->where('organization_id', $organizationId)
            ->where('bucket_id', $bucketId)
            ->where('user_id', $userId)
            ->where('status', self::STATUS_PENDING)
            ->latest('created_at')
            ->first();
    }

    /**
     * The member's latest answer for this bucket and period, whatever it is, so
     * the desktop can say "waiting" or "denied" instead of offering the ask
     * again.
     */
    public static function latestForMember(string $organizationId, string $bucketId, string $period, string $userId): ?self
    {
        return self::query()
            ->where('organization_id', $organizationId)
            ->where('bucket_id', $bucketId)
            ->where('period', $period)
            ->where('user_id', $userId)
            ->latest('created_at')
            ->first();
    }

    /**
     * Record an ask, or reopen this member's existing open one. One line per
     * person either way.
     */
    public static function ask(string $organizationId, string $bucketId, string $period, string $userId, string $reason): self
    {
        $existing = self::openFor($organizationId, $bucketId, $userId);

        if ($existing instanceof self) {
            $existing->forceFill(['reason' => $reason])->save();

            return $existing;
        }

        return self::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'bucket_id' => $bucketId,
            'period' => $period,
            'reason' => $reason,
            'status' => self::STATUS_PENDING,
        ]);
    }
}
