<?php

namespace App\Models;

use App\Support\AgentPermissionPolicy as Policy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An organization's agent permissions: what agents may run, edit, open and
 * change on its members' computers.
 *
 * The rows are the operator's intent; the rules the desktop enforces are
 * derived from them by {@see Policy::rules()}, never stored, so
 * a change to how a rule is expressed reaches every member without a backfill.
 *
 * @property string $id
 * @property string $organization_id
 * @property array<string, mixed> $settings
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AgentPermissionPolicy extends Model
{
    use HasUlids;

    protected $table = 'agent_permission_policies';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'settings',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * The organization's stored settings, or none when it has no policy yet.
     *
     * @return array<string, array{decision?: string, allow?: list<string>, block?: list<string>}>
     */
    public static function settingsFor(?string $organizationId): array
    {
        if ($organizationId === null) {
            return [];
        }

        $policy = self::query()->where('organization_id', $organizationId)->first();
        $settings = $policy instanceof self && is_array($policy->settings) ? $policy->settings : [];

        return Policy::normalize($settings);
    }

    /**
     * The ordered rules this organization's members' apps receive.
     *
     * @return list<array{action: string, resource: string, effect: string, source: string}>
     */
    public static function rulesFor(?string $organizationId): array
    {
        return Policy::rules(self::settingsFor($organizationId));
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
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
