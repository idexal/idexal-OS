<?php

namespace App\Models;

use Database\Factories\ProxyProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProxyProvider extends Model
{
    /** @use HasFactory<ProxyProviderFactory> */
    use HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'driver',
        'protocol',
        'website_url',
        'docs_url',
        'description',
        'countries',
        'config',
        'is_active',
        'is_public',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_proxy_provider')->withTimestamps();
    }

    /**
     * @param  Builder<ProxyProvider>  $query
     * @return Builder<ProxyProvider>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_public', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'driver' => $this->driver,
            'protocol' => $this->protocol,
            'websiteUrl' => $this->website_url,
            'docsUrl' => $this->docs_url,
            'description' => $this->description,
            'countries' => $this->countries ?? [],
        ];
    }
}
