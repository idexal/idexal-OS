<?php

namespace App\Models;

use Database\Factories\AiModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AiModel extends Model
{
    /** @use HasFactory<AiModelFactory> */
    use HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ai_provider_id',
        'model_id',
        'display_name',
        'description',
        'context_window',
        'max_output_tokens',
        'input_price',
        'output_price',
        'capabilities',
        'reasoning_options',
        'feature_overrides',
        'hybrid_config',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_price' => 'decimal:6',
            'output_price' => 'decimal:6',
            'capabilities' => 'array',
            'reasoning_options' => 'array',
            'feature_overrides' => 'array',
            'hybrid_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The gateway feature the operator pinned for this model alone: true to
     * force it on, false to force it off, null to inherit.
     */
    public function featureOverride(string $feature): ?bool
    {
        $overrides = $this->feature_overrides ?? [];
        $value = $overrides[$feature] ?? null;

        return $value === null ? null : (bool) $value;
    }

    public function supports(string $capability): bool
    {
        return (bool) data_get($this->capabilities, $capability, false);
    }

    public function costFor(int $promptTokens, int $completionTokens): float
    {
        $inputCost = ($promptTokens / 1_000_000) * (float) $this->input_price;
        $outputCost = ($completionTokens / 1_000_000) * (float) $this->output_price;

        return round($inputCost + $outputCost, 8);
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    /**
     * Empty means the model is available to every plan that can reach its
     * provider.
     *
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_ai_model')->withTimestamps();
    }
}
