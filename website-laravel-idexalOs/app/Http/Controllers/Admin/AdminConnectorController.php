<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ConnectorGrant;
use App\Models\ConnectorGrantRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\MarketplaceCatalogService;
use App\Services\NotificationService;
use App\Support\ConnectorGrantPolicy;
use App\Support\ConnectorScope;
use App\Support\DesktopSync;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What each installed connector may do for an organization.
 *
 * The marketplace declares what a connector needs; this is where an operator
 * answers. Nothing is approved by default, so a connector that asks for scopes
 * does nothing until an operator ticks them here. Revoking is the next revision
 * of the same grant — never a silent rename — and revoking never deletes the
 * row, so an old permission set cannot be replayed to bring it back.
 */
class AdminConnectorController extends Controller
{
    public function __construct(
        private readonly MarketplaceCatalogService $catalog,
        private readonly NotificationService $notifications,
    ) {}

    public function edit(Request $request): View
    {
        $organizations = Organization::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $organization = $this->selected($request, $organizations);

        return view('admin.connectors', [
            'organizations' => $organizations,
            'organization' => $organization,
            'connectors' => $organization instanceof Organization ? $this->connectors($organization) : [],
            'requests' => $organization instanceof Organization
                ? ConnectorGrantRequest::openForOrganization($organization->getKey())
                : collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organizations = Organization::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $organization = $this->selected($request, $organizations);

        if (! $organization instanceof Organization) {
            return redirect()->route('admin.connectors')
                ->withErrors(['organization' => __('ui.admin.connectors.needs_organization')]);
        }

        $validated = $request->validate([
            'organization' => ['required', 'string'],
            'slug' => ['required', 'string', 'max:190'],
            'action' => ['required', Rule::in(['grant', 'revoke'])],
            'scopes' => ['array'],
            'scopes.*' => ['array'],
            'scopes.*.*' => ['string', 'max:'.ConnectorScope::MAX_LENGTH],
            'reason' => ['nullable', 'string', 'max:500'],
            'expires_in' => ['nullable', Rule::in(array_merge(
                [ConnectorGrantPolicy::EXPIRY_NEVER],
                array_map('strval', ConnectorGrantPolicy::EXPIRY_DAYS),
            ))],
        ]);

        $slug = (string) $validated['slug'];
        $entry = $this->catalog->item($slug);

        if ($entry === null || ($entry['type'] ?? null) !== 'connector') {
            return back()->withErrors(['message' => __('ui.admin.connectors.not_found')]);
        }

        $connectorName = (string) ($entry['name'] ?? $slug);

        $requested = ConnectorGrantPolicy::requestedScopes($entry);
        $before = ConnectorGrant::bySlug($organization->getKey());
        $beforeSummaries = array_map(
            fn (ConnectorGrant $existing): string => ConnectorGrantPolicy::summary($existing),
            $before,
        );
        $grant = $before[$slug] ?? new ConnectorGrant([
            'organization_id' => (string) $organization->getKey(),
            'connector_slug' => $slug,
        ]);
        $revoking = (string) $validated['action'] === 'revoke';
        $submitted = $validated['scopes'][$slug] ?? [];

        if ($revoking) {
            $grant->status = ConnectorGrant::STATUS_REVOKED;
            $grant->reason = $this->nullableString($validated['reason'] ?? null) ?? __('ui.admin.connectors.reason_default');
            $grant->revoked_by = $request->user()?->getKey();
            $grant->revoked_at = now();
            // A revocation is final until an operator approves again, so it
            // carries no lease forward.
            $grant->lease_expires_at = null;
        } else {
            // An operator may approve only what the connector actually asked
            // for, in the order the catalogue declares it.
            $grant->scopes = array_values(array_intersect($requested, ConnectorGrantPolicy::normalizeScopes($submitted)));
            $grant->status = ConnectorGrant::STATUS_ACTIVE;
            $grant->reason = null;
            $grant->revoked_by = null;
            $grant->revoked_at = null;
            // The chosen duration starts now. Approving again is how an
            // expired or revoked grant comes back, and it gets a fresh lease.
            $grant->lease_expires_at = ConnectorGrantPolicy::expiryFromChoice($validated['expires_in'] ?? ConnectorGrantPolicy::EXPIRY_NEVER);
        }

        // Every change is a new revision of the organization's whole set.
        $grant->revision = ConnectorGrant::bumpRevision((string) $organization->getKey());
        $grant->updated_by = $request->user()?->getKey();
        $grant->save();

        // Approving the connector is the answer to every member who asked for
        // it, so their asks close together and each of them is told. A
        // revocation does not: access taken away is not a request granted.
        if (! $revoking) {
            $asking = ConnectorGrantRequest::openFor((string) $organization->getKey(), $slug);

            ConnectorGrantRequest::resolveFor(
                (string) $organization->getKey(),
                $slug,
                ConnectorGrantRequest::STATUS_APPROVED,
                $request->user()?->getKey(),
            );

            // A member who asked and waited should hear the answer where they
            // asked it, rather than discover it the next time they open the
            // Library. One notice per person, however the asks line up.
            foreach ($asking->unique('user_id') as $ask) {
                if (! $ask->user instanceof User) {
                    continue;
                }

                $this->notifications->send(
                    $ask->user,
                    'connector_request.approved',
                    __('ui.notifications.connector_approved_title', ['connector' => $connectorName]),
                    __('ui.notifications.connector_approved_body', [
                        'connector' => $connectorName,
                        'organization' => $organization->name,
                    ]),
                    route('dashboard.notifications'),
                    $organization,
                );
            }
        }

        $after = ConnectorGrant::bySlug($organization->getKey());
        $afterSummaries = array_map(
            fn (ConnectorGrant $existing): string => ConnectorGrantPolicy::summary($existing),
            $after,
        );
        $changes = ConnectorGrantPolicy::changes($beforeSummaries, $afterSummaries);

        DesktopSync::announce('connector_grants_updated', ['organizationId' => $organization->getKey()]);

        if ($changes === []) {
            return redirect()->route('admin.connectors', ['organization' => $organization->getKey()])
                ->with('status', __('ui.common.saved'));
        }

        AuditLog::record(
            $revoking ? 'admin.connector_grant.revoked' : 'admin.connector_grant.granted',
            $organization,
            $grant,
            ConnectorGrantPolicy::describe($changes) ?? __('ui.admin.connectors.title'),
            ['connector_grants' => $changes],
        );

        return redirect()->route('admin.connectors', ['organization' => $organization->getKey()])
            ->with('status', __('ui.common.saved'));
    }

    /**
     * An operator decides not to approve a connector, closing one member's ask
     * without touching the grant. The member may ask again, which is the point:
     * dismissing is a decision, not a block.
     */
    public function dismissRequest(Request $request): RedirectResponse
    {
        $organizations = Organization::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $organization = $this->selected($request, $organizations);

        if (! $organization instanceof Organization) {
            return redirect()->route('admin.connectors')
                ->withErrors(['organization' => __('ui.admin.connectors.needs_organization')]);
        }

        $validated = $request->validate([
            'request' => ['required', 'string', 'max:26'],
        ]);

        $ask = ConnectorGrantRequest::query()
            ->where('organization_id', $organization->getKey())
            ->where('id', (string) $validated['request'])
            ->where('status', ConnectorGrantRequest::STATUS_OPEN)
            ->first();

        if (! $ask instanceof ConnectorGrantRequest) {
            return redirect()->route('admin.connectors', ['organization' => $organization->getKey()])
                ->withErrors(['message' => __('ui.admin.connectors.request_not_found')]);
        }

        $ask->status = ConnectorGrantRequest::STATUS_DISMISSED;
        $ask->resolved_by = $request->user()?->getKey();
        $ask->resolved_at = now();
        $ask->save();

        $entry = $this->catalog->item((string) $ask->connector_slug);
        $connectorName = (string) ($entry['name'] ?? $ask->connector_slug);

        // A dismissal closes the ask, so the member who made it is told — and
        // told they may ask again, because dismissing is a decision, not a
        // block.
        if ($ask->user instanceof User) {
            $this->notifications->send(
                $ask->user,
                'connector_request.declined',
                __('ui.notifications.connector_declined_title', ['connector' => $connectorName]),
                __('ui.notifications.connector_declined_body', [
                    'connector' => $connectorName,
                    'organization' => $organization->name,
                ]),
                route('dashboard.notifications'),
                $organization,
            );
        }

        AuditLog::record(
            'admin.connector_request.dismissed',
            $organization,
            $ask,
            __('ui.admin.connectors.request_dismiss_audit', ['connector' => $ask->connector_slug]),
            ['connector_grants' => [
                (string) $ask->connector_slug => [
                    'from' => __('ui.admin.connectors.summary_requested'),
                    'to' => __('ui.admin.connectors.summary_request_dismissed'),
                ],
            ]],
        );

        return redirect()->route('admin.connectors', ['organization' => $organization->getKey()])
            ->with('status', __('ui.common.saved'));
    }

    /**
     * Every catalogue connector that asks for scopes, with the scopes it asks
     * for and what the organization has approved so far.
     *
     * @return list<array<string, mixed>>
     */
    private function connectors(Organization $organization): array
    {
        $grants = ConnectorGrant::bySlug($organization->getKey());
        $rows = [];

        foreach ($this->catalog->catalog()['items'] as $entry) {
            if (($entry['type'] ?? null) !== 'connector') {
                continue;
            }

            $requested = ConnectorGrantPolicy::requestedScopes($entry);

            if ($requested === []) {
                continue;
            }

            $grant = $grants[(string) $entry['slug']] ?? null;
            $approvedScopes = $grant instanceof ConnectorGrant ? array_values((array) $grant->scopes) : [];

            $rows[] = [
                'slug' => (string) $entry['slug'],
                'name' => (string) ($entry['name'] ?? $entry['slug']),
                'description' => (string) ($entry['description'] ?? ''),
                'scopes' => array_map(fn (string $value): array => [
                    'value' => $value,
                    'label' => ConnectorScope::parse($value)?->label() ?? $value,
                    'approved' => in_array($value, $approvedScopes, true),
                ], $requested),
                'status' => $grant?->status,
                'revision' => $grant?->revision,
                'reason' => $grant?->reason,
                'expiresAt' => $grant?->lease_expires_at,
                'expired' => $grant instanceof ConnectorGrant
                    && ($grant->status === ConnectorGrant::STATUS_EXPIRED || $grant->isExpired()),
                'expiryChoice' => ConnectorGrantPolicy::expiryChoiceFor($grant),
                'updatedAt' => $grant?->updated_at,
            ];
        }

        return $rows;
    }

    /**
     * @param  iterable<Organization>  $organizations
     */
    private function selected(Request $request, iterable $organizations): ?Organization
    {
        // The picker submits in the query string; the form carries the same id
        // in the body, so a save applies to the workspace it was rendered for.
        $id = (string) ($request->query('organization') ?: $request->input('organization', ''));

        foreach ($organizations as $organization) {
            if ($id !== '' && $organization->getKey() === $id) {
                return $organization;
            }
        }

        foreach ($organizations as $organization) {
            return $organization;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
