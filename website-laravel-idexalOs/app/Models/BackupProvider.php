<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operator-defined failover link: when the main provider cannot answer, the
 * gateway tries the backup provider next, in ascending priority order, before
 * it moves on to any other provider that offers the same model.
 */
class BackupProvider extends Model
{
    protected $table = 'backup_providers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'main_provider_id',
        'backup_provider_id',
        'priority',
        'health_check_url',
        'max_failures_before_disable',
        'failback_delay_minutes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'max_failures_before_disable' => 'integer',
            'failback_delay_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function mainProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'main_provider_id');
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function backupProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'backup_provider_id');
    }
}
