<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public const TYPE_PERSONAL = 'personal';

    public const TYPE_TEAM = 'team';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'type',
        'status',
        'billing_email',
        'currency',
        'country',
        'city',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'tax_id',
        'stripe_customer_id',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_balance' => 'decimal:6',
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
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

    public function isPersonal(): bool
    {
        return $this->type === self::TYPE_PERSONAL;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<PaymentMethod, $this>
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return HasMany<UsageCounter, $this>
     */
    public function usageCounters(): HasMany
    {
        return $this->hasMany(UsageCounter::class);
    }

    /**
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * @return HasMany<WebhookEndpoint, $this>
     */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    /**
     * @return HasMany<DesktopSession, $this>
     */
    public function desktopSessions(): HasMany
    {
        return $this->hasMany(DesktopSession::class);
    }

    public function membershipFor(User $user): ?Membership
    {
        return $this->memberships()
            ->where('user_id', $user->getKey())
            ->first();
    }

    public function roleFor(User $user): ?string
    {
        return $this->membershipFor($user)?->role;
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->with('plan')
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_PAST_DUE,
            ])
            ->latest('created_at')
            ->get()
            ->first(fn (Subscription $subscription): bool => $subscription->grantsAccess());
    }

    public function currentPlan(): ?Plan
    {
        $subscriptionPlan = $this->currentSubscription()?->plan;

        if ($subscriptionPlan instanceof Plan) {
            return $subscriptionPlan;
        }

        return Plan::query()->where('slug', 'free')->first();
    }

    public function creditBalance(): float
    {
        return (float) $this->credit_balance;
    }

    public function hasActiveMembership(User $user): bool
    {
        return $this->membershipFor($user)?->isActive() ?? false;
    }
}
