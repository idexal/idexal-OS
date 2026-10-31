<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A grant of plugin access inside one workspace.
 *
 * Reading and writing access is what the desktop's Library does, so the rules
 * live here and nowhere else:
 *
 * - a plugin with no grants is available to the whole workspace, which is the
 *   behaviour before this table existed and keeps an operator's first install
 *   working;
 * - an org-wide grant says the same thing explicitly;
 * - a grant for one membership narrows the plugin to the people named.
 */
class PluginAccessGrant extends Model
{
    use HasUlids;

    /** The roles the desktop knows. */
    public const ROLES = ['viewer', 'editor', 'manager', 'owner'];

    public const DEFAULT_ROLE = 'viewer';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'plugin_slug',
        'membership_id',
        'org_wide',
        'role',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'org_wide' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    /**
     * Every grant in one workspace, grouped by plugin slug.
     *
     * @return Collection<string, Collection<int, self>>
     */
    public static function bySlug(string $organizationId): Collection
    {
        return self::query()
            ->where('organization_id', $organizationId)
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (self $grant): string => (string) $grant->plugin_slug);
    }

    /**
     * The slugs a member may use, as a set. A slug absent from the set but
     * present in `$known` is denied; a slug that has no grants at all is not in
     * `$known` and is therefore allowed.
     *
     * @param  Collection<string, Collection<int, self>>  $bySlug
     * @return array{allowed: array<string, bool>, known: array<string, bool>}
     */
    public static function decisionsFor(Collection $bySlug, ?string $membershipId): array
    {
        $allowed = [];
        $known = [];

        foreach ($bySlug as $slug => $grants) {
            $known[$slug] = true;
            $allowed[$slug] = $grants->contains(fn (self $grant): bool => $grant->org_wide === true)
                || ($membershipId !== null && $grants->contains(
                    fn (self $grant): bool => (string) $grant->membership_id === $membershipId
                ));
        }

        return ['allowed' => $allowed, 'known' => $known];
    }

    /**
     * Grant a plugin to the whole workspace, replacing any existing org-wide
     * row so a repeated click updates one line instead of stacking.
     */
    public static function grantOrgWide(string $organizationId, string $slug, string $role, ?string $actorId): self
    {
        return self::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'plugin_slug' => $slug, 'membership_id' => null],
            ['org_wide' => true, 'role' => $role, 'created_by' => $actorId],
        );
    }

    /**
     * Grant a plugin to one member of the workspace.
     */
    public static function grantToMembership(string $organizationId, string $slug, string $membershipId, string $role, ?string $actorId): self
    {
        return self::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'plugin_slug' => $slug, 'membership_id' => $membershipId],
            ['org_wide' => false, 'role' => $role, 'created_by' => $actorId],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => (string) $this->getKey(),
            'orgWide' => (bool) $this->org_wide,
            'orgMembershipId' => $this->membership_id !== null ? (string) $this->membership_id : null,
            'teamId' => null,
            'role' => in_array((string) $this->role, self::ROLES, true) ? (string) $this->role : self::DEFAULT_ROLE,
        ];
    }
}
