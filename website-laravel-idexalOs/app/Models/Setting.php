<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'encrypted',
        'group',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // Cache the raw attributes, never the Eloquent model: persistent
        // stores unserialize a model as __PHP_Incomplete_Class, which made
        // every admin setting fall back to its default after the first read.
        $attributes = Cache::rememberForever(
            self::cacheKey($key),
            fn (): ?array => self::query()->find($key)?->getAttributes(),
        );

        if (! is_array($attributes)) {
            return $default;
        }

        return self::decodeAttributes($attributes, $default);
    }

    public static function put(string $key, mixed $value, bool $encrypted = false, string $group = 'general'): self
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $setting = self::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $encrypted ? Crypt::encryptString((string) $encoded) : $encoded,
                'encrypted' => $encrypted,
                'group' => $group,
            ],
        );

        Cache::forget(self::cacheKey($key));

        return $setting;
    }

    public static function forget(string $key): void
    {
        self::query()->whereKey($key)->delete();
        Cache::forget(self::cacheKey($key));
    }

    public static function flushCache(): void
    {
        $keys = self::query()->pluck('key');

        foreach ($keys as $key) {
            Cache::forget(self::cacheKey($key));
        }
    }

    public function decodedValue(mixed $default = null): mixed
    {
        return self::decodeAttributes($this->getAttributes(), $default);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function decodeAttributes(array $attributes, mixed $default): mixed
    {
        $raw = (string) ($attributes['value'] ?? '');

        if (($attributes['encrypted'] ?? false) && $raw !== '') {
            $raw = Crypt::decryptString($raw);
        }

        if ($raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    public static function cacheKey(string $key): string
    {
        return 'setting:'.$key;
    }
}
