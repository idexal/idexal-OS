<?php

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory, HasUlids;

    public const ABILITY_ALL = '*';

    public const ABILITY_DESKTOP = 'desktop';

    public const ABILITY_GATEWAY_CHAT = 'gateway:chat';

    public const ABILITY_GATEWAY_MODELS = 'gateway:models';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'key_prefix',
        'key_hash',
        'abilities',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'key_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @param  list<string>  $abilities
     * @return array{0: self, 1: string}
     */
    public static function issue(
        Organization $organization,
        User $user,
        string $name,
        array $abilities = [self::ABILITY_GATEWAY_CHAT, self::ABILITY_GATEWAY_MODELS],
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        $environment = app()->isProduction() ? 'live' : 'test';
        $plain = 'ixo_'.$environment.'_'.Str::random(40);

        $apiKey = self::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'name' => $name,
            'key_prefix' => mb_substr($plain, 0, 16),
            'key_hash' => hash('sha256', $plain),
            'abilities' => array_values(array_unique($abilities)),
            'expires_at' => $expiresAt,
        ]);

        return [$apiKey, $plain];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        return in_array(self::ABILITY_ALL, $abilities, true)
            || in_array($ability, $abilities, true)
            || in_array(Str::before($ability, ':').':*', $abilities, true);
    }

    public function markUsed(): void
    {
        if ($this->last_used_at !== null && $this->last_used_at->gt(now()->subMinute())) {
            return;
        }

        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function maskedPrefix(): string
    {
        return $this->key_prefix.'••••••••';
    }

    public function verify(string $plain): bool
    {
        return hash_equals($this->key_hash, hash('sha256', $plain));
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
}
