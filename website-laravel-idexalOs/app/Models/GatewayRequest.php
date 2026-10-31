<?php

namespace App\Models;

use App\Support\GatewayFeature;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayRequest extends Model
{
    use HasUlids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'organization_id',
        'user_id',
        'api_key_id',
        'ai_provider_id',
        'ai_model_id',
        'endpoint',
        'model',
        'status_code',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost',
        'latency_ms',
        'is_stream',
        'error',
        'meta',
        'ip',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:8',
            'is_stream' => 'boolean',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Feature names the gateway recorded for this request, in recorded order.
     * Names the platform no longer knows (written by an older gateway, or after
     * a feature was renamed) are dropped so views never render a raw
     * translation key to an operator or a customer.
     *
     * @return list<string>
     */
    public function appliedFeatures(): array
    {
        $recorded = array_filter(
            (array) ($this->meta['features'] ?? []),
            fn ($feature) => is_string($feature) && in_array($feature, GatewayFeature::keys(), true),
        );

        return array_values(array_unique($recorded));
    }

    /**
     * The flat search fee the gateway added on top of this request's token cost,
     * or 0.0 when no search ran. The fee is read from the record captured at
     * request time, so changing the price later never rewrites an old row.
     */
    public function searchFee(): float
    {
        $fee = $this->meta['web_search_price'] ?? null;

        return is_numeric($fee) ? (float) $fee : 0.0;
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
     * @return BelongsTo<ApiKey, $this>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    /**
     * @return BelongsTo<AiModel, $this>
     */
    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }
}
