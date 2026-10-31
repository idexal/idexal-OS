<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageCounter extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'period',
        'requests',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requests' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost' => 'decimal:8',
        ];
    }

    public static function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    public static function forOrganization(Organization $organization, ?string $period = null): self
    {
        return self::query()->firstOrCreate(
            [
                'organization_id' => $organization->getKey(),
                'period' => $period ?? self::currentPeriod(),
            ],
            [
                'requests' => 0,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'cost' => 0,
            ],
        );
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
