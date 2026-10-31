<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUlids;

    public const LIMIT_REQUESTS = 'requests_per_month';

    public const LIMIT_TOKENS = 'tokens_per_month';

    public const LIMIT_SEATS = 'seats';

    public const LIMIT_API_KEYS = 'api_keys';

    public const LIMIT_PROXY_PROFILES = 'proxy_profiles';

    public const LIMIT_PROXY_BANDWIDTH_GB = 'proxy_bandwidth_gb';

    /**
     * A monthly spend allowance, in USD. A plan that sets it gives the desktop
     * a budget to show against the period's cost, and a member who exhausts it
     * can ask an operator for more.
     */
    public const LIMIT_MONTHLY_COST_USD = 'monthly_cost_usd';

    public const FEATURE_PROXY = 'proxy';

    public const FEATURE_PROXY_RESIDENTIAL = 'proxy_residential';

    public const FEATURE_PROXY_ROTATION = 'proxy_rotation';

    public const FEATURE_PROXY_FINGERPRINT = 'proxy_fingerprint';

    public const FEATURE_BROWSER_PREWARM = 'browser_prewarm';

    public const FEATURE_CUSTOM_PROVIDERS = 'custom_providers';

    public const FEATURE_MULTIPLE_WORKSPACES = 'multiple_workspaces';

    public const FEATURE_MANAGE_EXTENSIONS = 'manage_extensions';

    public const FEATURE_AGENT_CONTROL = 'agent_control';

    public const FEATURE_AGENT_SETTINGS = 'agent_settings';

    public const FEATURE_AGENT_PROXY = 'agent_proxy';

    public const FEATURE_AGENT_EXTENSIONS = 'agent_extensions';

    public const FEATURE_AGENT_BROWSER = 'agent_browser';

    public const FEATURE_AGENT_PROVIDERS = 'agent_providers';

    /**
     * A plan that grants this lets a signed-in user open the desktop app at all.
     * Absent from `features` means the plan was created before the gate existed,
     * so it is treated as granting access (backward compatible). The gate itself
     * only becomes visible once the `accountRequiredForDesktopAccess` switch is
     * on; while that switch is off every plan grants access regardless of this
     * key.
     */
    public const FEATURE_DESKTOP_ACCESS = 'desktop_access';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'currency',
        'features',
        'limits',
        'stripe_price_monthly_id',
        'stripe_price_yearly_id',
        'is_public',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'features' => 'array',
            'limits' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function isFree(): bool
    {
        return (float) $this->price_monthly === 0.0;
    }

    public function priceFor(string $cycle): float
    {
        return (float) ($cycle === 'yearly' ? $this->price_yearly : $this->price_monthly);
    }

    public function formattedPrice(string $cycle = 'monthly'): string
    {
        if ($this->isFree()) {
            return __('ui.billing.free');
        }

        return money($this->priceFor($cycle), $this->currency);
    }

    public function limit(string $key, mixed $default = null): mixed
    {
        return data_get($this->limits, $key, $default);
    }

    public function hasFeature(string $key): bool
    {
        return (bool) data_get($this->features, $key, false);
    }

    /**
     * @return list<string>
     */
    public function featureList(): array
    {
        return collect($this->features ?? [])
            ->filter(fn (mixed $value): bool => $value === true)
            ->keys()
            ->all();
    }

    public function stripePriceId(string $cycle): ?string
    {
        return $cycle === 'yearly' ? $this->stripe_price_yearly_id : $this->stripe_price_monthly_id;
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return BelongsToMany<ProxyProvider, $this>
     */
    public function proxyProviders(): BelongsToMany
    {
        return $this->belongsToMany(ProxyProvider::class, 'plan_proxy_provider')->withTimestamps();
    }

    /**
     * @return BelongsToMany<AiProvider, $this>
     */
    public function aiProviders(): BelongsToMany
    {
        return $this->belongsToMany(AiProvider::class, 'plan_ai_provider')->withTimestamps();
    }

    /**
     * @return BelongsToMany<AiModel, $this>
     */
    public function aiModels(): BelongsToMany
    {
        return $this->belongsToMany(AiModel::class, 'plan_ai_model')->withTimestamps();
    }
}
