<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One health probe result for a provider. Rows accumulate over time so the
 * admin screen can show the newest status and later plot a history.
 */
class ProviderHealthLog extends Model
{
    protected $table = 'provider_health_logs';

    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_UNHEALTHY = 'unhealthy';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ai_provider_id',
        'check_time',
        'status',
        'response_time_ms',
        'error_rate',
        'quota_remaining',
        'error_message',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_time' => 'datetime',
            'status' => 'string',
            'response_time_ms' => 'integer',
            'error_rate' => 'float',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }
}
