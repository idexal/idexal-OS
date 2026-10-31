<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra API key for a provider. A provider also has one default key on its
 * own row; this table holds any number of additional keys so the gateway can
 * move on to the next one when a key runs out of quota or is rejected.
 */
class ProviderApiKey extends Model
{
    public const TYPE_PRIMARY = 'primary';

    public const TYPE_BACKUP = 'backup';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ai_provider_id',
        'key_name',
        'key_type',
        'api_key_encrypted',
        'last_used_at',
        'expires_at',
        'is_active',
        'rotation_count',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'api_key_encrypted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key_encrypted' => 'encrypted',
            'is_active' => 'boolean',
            'rotation_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_PRIMARY => 'primary',
            self::TYPE_BACKUP => 'backup',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Keys the gateway may use right now: switched on and not past their
     * expiry date.
     *
     * @param  Builder<ProviderApiKey>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function maskedValue(): string
    {
        $key = (string) $this->api_key_encrypted;

        if ($key === '') {
            return '—';
        }

        if (mb_strlen($key) <= 8) {
            return str_repeat('•', mb_strlen($key));
        }

        return mb_substr($key, 0, 4).'••••'.mb_substr($key, -4);
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }
}
