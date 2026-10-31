<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What one installed connector may do for one organization, approved by an
 * operator and stamped with the revision it was approved at.
 *
 * The row is the operator's answer, not the desktop's enforcement: the desktop
 * compares the revision and the status, and a revoked grant stops the
 * connector. Revoking never deletes the row — the next approval is the next
 * revision of the same grant, so nobody can restore a revoked set by replaying
 * an old one.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $connector_slug
 * @property list<string> $scopes
 * @property int $revision
 * @property string $status
 * @property string|null $reason
 * @property Carbon|null $lease_expires_at
 * @property string|null $updated_by
 * @property string|null $revoked_by
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConnectorGrant extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    /**
     * An approval whose lease ran out. The row stays — the operator's answer
     * with its scopes — so re-approving is a new revision of the same grant,
     * exactly like revoking.
     */
    public const STATUS_EXPIRED = 'expired';

    protected $table = 'connector_grants';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'connector_slug',
        'scopes',
        'revision',
        'status',
        'reason',
        'lease_expires_at',
        'updated_by',
        'revoked_by',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'revision' => 'integer',
            'lease_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Every grant of the organization, keyed by connector slug.
     *
     * @return array<string, self>
     */
    public static function bySlug(?string $organizationId): array
    {
        if ($organizationId === null) {
            return [];
        }

        $grants = [];

        foreach (self::query()->where('organization_id', $organizationId)->get() as $grant) {
            $grants[(string) $grant->connector_slug] = $grant;
        }

        return $grants;
    }

    /**
     * The block a member's desktop receives at `/v1/me/desktop-config`. The
     * `revision` orders the whole set: a desktop replaces what it holds only
     * when the incoming revision is newer, so an older set arriving late — a
     * stale inventory event — can never bring a revoked grant back. `scoped`
     * names every catalogue connector that asks for scopes, so a desktop can
     * refuse one that was never approved without trusting a client's claim.
     *
     * @param  list<string>  $scoped
     * @return array{revision: int, scoped: list<string>, grants: list<array{slug: string, revision: int, status: string, scopes: list<string>, expiresAt: string|null}>}
     */
    public static function permissionsFor(?string $organizationId, array $scoped = []): array
    {
        if ($organizationId === null) {
            return ['revision' => 0, 'scoped' => array_values($scoped), 'grants' => []];
        }

        $grants = self::query()
            ->where('organization_id', $organizationId)
            ->orderBy('connector_slug')
            ->get();

        return [
            'revision' => self::revisionFor($organizationId),
            'scoped' => array_values($scoped),
            'grants' => $grants
                ->map(fn (self $grant): array => [
                    'slug' => (string) $grant->connector_slug,
                    'revision' => (int) $grant->revision,
                    // An approval whose lease passed is delivered as expired
                    // even before the scheduled command flips the row, so an
                    // approval that was meant to lapse cannot outlive its
                    // deadline just because the platform has not swept yet.
                    'status' => $grant->isExpired() ? self::STATUS_EXPIRED : (string) $grant->status,
                    'scopes' => array_values((array) $grant->scopes),
                    'expiresAt' => $grant->lease_expires_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Whether the approval's lease has run out at `$at`. A grant that was never
     * given a duration — or one that is revoked — never expires.
     */
    public function isExpired(?Carbon $at = null): bool
    {
        if ((string) $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $lease = $this->lease_expires_at;

        if (! $lease instanceof Carbon) {
            return false;
        }

        return $lease->lessThanOrEqualTo($at ?? now());
    }

    /** The organization's current set revision, or 0 when it has never been revised. */
    public static function revisionFor(?string $organizationId): int
    {
        if ($organizationId === null) {
            return 0;
        }

        return (int) DB::table('connector_grant_revisions')
            ->where('organization_id', $organizationId)
            ->value('revision');
    }

    /**
     * The next revision for the organization, written atomically so two
     * operators saving at once cannot land on the same number.
     */
    public static function bumpRevision(string $organizationId): int
    {
        return DB::transaction(function () use ($organizationId): int {
            $row = DB::table('connector_grant_revisions')
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('connector_grant_revisions')->insert([
                    'organization_id' => $organizationId,
                    'revision' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->revision + 1;

            DB::table('connector_grant_revisions')
                ->where('organization_id', $organizationId)
                ->update(['revision' => $next, 'updated_at' => now()]);

            return $next;
        });
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
