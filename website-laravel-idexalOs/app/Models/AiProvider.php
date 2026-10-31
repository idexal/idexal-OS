<?php

namespace App\Models;

use Database\Factories\AiProviderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AiProvider extends Model
{
    /** @use HasFactory<AiProviderFactory> */
    use HasFactory, HasUlids;

    public const DRIVER_OPENAI = 'openai';

    public const DRIVER_ANTHROPIC = 'anthropic';

    public const DRIVER_OPENAI_COMPATIBLE = 'openai_compatible';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'driver',
        'base_url',
        'api_key',
        'headers',
        'is_active',
        'priority',
        'supports_streaming',
        'feature_overrides',
        'notes',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'headers' => 'array',
            'is_active' => 'boolean',
            'supports_streaming' => 'boolean',
            'feature_overrides' => 'array',
        ];
    }

    /**
     * The gateway feature the operator pinned for this provider: true to force
     * it on, false to force it off, null to inherit the platform setting.
     */
    public function featureOverride(string $feature): ?bool
    {
        $overrides = $this->feature_overrides ?? [];
        $value = $overrides[$feature] ?? null;

        return $value === null ? null : (bool) $value;
    }

    /**
     * @return array<string, string>
     */
    public static function drivers(): array
    {
        return [
            self::DRIVER_OPENAI => 'OpenAI',
            self::DRIVER_OPENAI_COMPATIBLE => 'OpenAI compatible',
            self::DRIVER_ANTHROPIC => 'Anthropic',
        ];
    }

    public function isPlatform(): bool
    {
        return $this->organization_id === null;
    }

    public function endpoint(string $path = ''): string
    {
        return rtrim($this->base_url, '/').'/'.ltrim($path, '/');
    }

    public function maskedKey(): string
    {
        $key = (string) $this->api_key;

        if ($key === '') {
            return '—';
        }

        if (mb_strlen($key) <= 8) {
            return str_repeat('•', mb_strlen($key));
        }

        return mb_substr($key, 0, 4).'••••'.mb_substr($key, -4);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<AiModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    /**
     * Extra API keys beyond the provider's own default key.
     *
     * @return HasMany<ProviderApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ProviderApiKey::class, 'ai_provider_id');
    }

    /**
     * Failover links where this provider is the main one.
     *
     * @return HasMany<BackupProvider, $this>
     */
    public function backupLinks(): HasMany
    {
        return $this->hasMany(BackupProvider::class, 'main_provider_id');
    }

    /**
     * Failover links where this provider is the backup.
     *
     * @return HasMany<BackupProvider, $this>
     */
    public function incomingBackupLinks(): HasMany
    {
        return $this->hasMany(BackupProvider::class, 'backup_provider_id');
    }

    /**
     * Backup providers this provider hands over to, in the order they should
     * be tried.
     *
     * @return Collection<int, AiProvider>
     */
    public function backupProviders(): Collection
    {
        return $this->backupLinks()
            ->with('backupProvider')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (BackupProvider $link): ?AiProvider => $link->backupProvider)
            ->filter(fn (?AiProvider $provider): bool => $provider instanceof AiProvider)
            ->values();
    }

    /**
     * Empty means the provider is available to every plan.
     *
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_ai_provider')->withTimestamps();
    }
}
