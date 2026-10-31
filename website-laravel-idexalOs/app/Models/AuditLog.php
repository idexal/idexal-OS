<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'ip',
        'user_agent',
        'meta',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        string $action,
        ?Organization $organization = null,
        ?Model $subject = null,
        ?string $description = null,
        array $meta = [],
    ): self {
        $user = Auth::user();

        return self::query()->create([
            'organization_id' => $organization?->getKey(),
            'user_id' => $user?->getKey(),
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'ip' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 1000),
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    /**
     * The before/after sections an entry records, when their shape is
     * field => {from, to}. The audit page renders these as a table instead of raw
     * JSON, because a settings save can move a dozen fields at once.
     *
     * @return array<string, array<string, array{from: mixed, to: mixed}>>
     */
    public function changeSections(): array
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $sections = [];

        foreach (['features', 'settings', 'agent_permissions', 'connector_grants'] as $section) {
            $rows = $meta[$section] ?? null;

            if (! is_array($rows) || $rows === []) {
                continue;
            }

            foreach ($rows as $change) {
                if (! is_array($change) || ! array_key_exists('from', $change) || ! array_key_exists('to', $change)) {
                    continue 2;
                }
            }

            $sections[$section] = $rows;
        }

        return $sections;
    }

    /**
     * Metadata that is not part of a before/after section, so the page can still
     * show it as JSON.
     *
     * @return array<string, mixed>
     */
    public function otherMeta(): array
    {
        return array_diff_key(is_array($this->meta) ? $this->meta : [], $this->changeSections());
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
