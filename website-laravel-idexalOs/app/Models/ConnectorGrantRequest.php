<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One member's ask for a connector the organization has not approved.
 *
 * A request is not a grant: it records who wants a connector and the scopes the
 * marketplace declares, and it sits in an operator's queue until they approve or
 * dismiss it. One row per (organization, connector, member), so a member who
 * asks twice reopens the same ask instead of queueing a second. Approving the
 * connector resolves every open ask for it at once, because the answer is the
 * grant, not the person.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $connector_slug
 * @property string $user_id
 * @property list<string> $scopes
 * @property string $status
 * @property string|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConnectorGrantRequest extends Model
{
    use HasUlids;

    public const STATUS_OPEN = 'open';

    /** Resolved by approving the connector, not by touching the request. */
    public const STATUS_APPROVED = 'approved';

    /** An operator decided not to approve it. The member may ask again. */
    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'connector_grant_requests';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'connector_slug',
        'user_id',
        'scopes',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Every open ask for the organization, newest first, so the operator's
     * queue reads top-down.
     *
     * @return Collection<int, self>
     */
    public static function openForOrganization(?string $organizationId)
    {
        if ($organizationId === null) {
            return self::query()->whereRaw('1 = 0')->get();
        }

        return self::query()
            ->with('user')
            ->where('organization_id', $organizationId)
            ->where('status', self::STATUS_OPEN)
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * One member's asks, keyed by connector slug, so a desktop can say
     * "approval requested" without a second lookup.
     *
     * @return array<string, self>
     */
    public static function forMember(?string $organizationId, ?string $userId): array
    {
        if ($organizationId === null || $userId === null) {
            return [];
        }

        $requests = [];

        foreach (self::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', self::STATUS_OPEN)
            ->get() as $request) {
            $requests[(string) $request->connector_slug] = $request;
        }

        return $requests;
    }

    /**
     * Every open ask for one connector, with the member who made it, so the
     * operator who answers can tell each of them. Gathered before the asks are
     * resolved, since resolving clears the status this filters on.
     *
     * @return Collection<int, self>
     */
    public static function openFor(string $organizationId, string $slug): Collection
    {
        return self::query()
            ->with('user')
            ->where('organization_id', $organizationId)
            ->where('connector_slug', $slug)
            ->where('status', self::STATUS_OPEN)
            ->get();
    }

    /**
     * Record a member's ask, or reopen one they already made. The scopes come
     * from the catalogue, never from the caller: the member asks for the
     * connector, and what it needs is the marketplace's answer.
     *
     * @param  list<string>  $scopes
     */
    public static function ask(string $organizationId, string $slug, string $userId, array $scopes): self
    {
        $request = self::query()
            ->where('organization_id', $organizationId)
            ->where('connector_slug', $slug)
            ->where('user_id', $userId)
            ->first() ?? new self([
                'organization_id' => $organizationId,
                'connector_slug' => $slug,
                'user_id' => $userId,
            ]);

        $request->scopes = array_values($scopes);
        $request->status = self::STATUS_OPEN;
        $request->resolved_by = null;
        $request->resolved_at = null;
        $request->save();

        return $request;
    }

    /**
     * Close every open ask for a connector, whether it was approved or
     * dismissed. Returns how many rows moved, so a caller can decide whether
     * the change is worth an audit line.
     */
    public static function resolveFor(string $organizationId, string $slug, string $status, ?string $resolvedBy = null): int
    {
        return self::query()
            ->where('organization_id', $organizationId)
            ->where('connector_slug', $slug)
            ->where('status', self::STATUS_OPEN)
            ->update([
                'status' => $status,
                'resolved_by' => $resolvedBy,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
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
