<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_UNPAID = 'unpaid';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'plan_id',
        'status',
        'billing_cycle',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'canceled_at',
        'stripe_subscription_id',
        'stripe_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_INCOMPLETE;
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function hasEnded(): bool
    {
        return $this->canceled_at !== null && $this->current_period_end !== null && $this->current_period_end->isPast();
    }

    /**
     * Whether this subscription still grants its plan's features. Past-due
     * subscriptions keep a short grace window; canceled-at-period-end and
     * abandoned incomplete checkouts do not.
     */
    public function grantsAccess(): bool
    {
        if (! in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING, self::STATUS_PAST_DUE], true)) {
            return false;
        }

        if ($this->status === self::STATUS_TRIALING
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast()) {
            return false;
        }

        if ($this->canceled_at !== null && $this->hasEnded()) {
            return false;
        }

        if ($this->status === self::STATUS_PAST_DUE
            && $this->current_period_end !== null
            && $this->current_period_end->copy()->addDays(7)->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
