<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentPermissionPolicy as AgentPermissionPolicyModel;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AuditLog;
use App\Models\ConnectorGrant;
use App\Models\ConnectorGrantRequest;
use App\Models\ExtensionInstallation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PluginAccessGrant;
use App\Models\User;
use App\Services\MarketplaceCatalogService;
use App\Services\NotificationService;
use App\Services\OrganizationContext;
use App\Services\ProviderEntitlementService;
use App\Services\ProxyEntitlementService;
use App\Services\SettingsService;
use App\Support\ConnectorGrantPolicy;
use App\Support\DesktopSwitch;
use App\Support\DesktopSync;
use App\Support\PlatformFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MeController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly SettingsService $settings,
        private readonly ProxyEntitlementService $proxyEntitlements,
        private readonly ProviderEntitlementService $providerEntitlements,
        private readonly MarketplaceCatalogService $marketplace,
        private readonly NotificationService $notifications,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($this->user($request)),
        ]);
    }

    public function orgs(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $organization = $this->context->requireOrganization();
        $current = $this->context->membership();

        $memberships = Membership::query()
            ->with('organization')
            ->where('user_id', $user->getKey())
            ->where('status', Membership::STATUS_ACTIVE)
            ->get()
            ->filter(fn (Membership $membership): bool => $membership->organization instanceof Organization
                && ! $membership->organization->isSuspended())
            ->values();

        return response()->json([
            'orgs' => $memberships->map(fn (Membership $membership): array => [
                'id' => $membership->organization->getKey(),
                'name' => $membership->organization->name,
                'slug' => $membership->organization->slug,
                'role' => $membership->role,
            ])->values()->all(),
            'activeOrgId' => $organization->getKey(),
            'activeOrgSlug' => $organization->slug,
            'defaultOrgId' => $current instanceof Membership
                ? $organization->getKey()
                : $memberships->first()?->organization_id,
        ]);
    }

    public function activeOrganization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organizationId' => ['nullable', 'string', 'max:26'],
            'organizationSlug' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $this->user($request);
        $query = Organization::query()->where('status', Organization::STATUS_ACTIVE);

        if (filled($validated['organizationId'] ?? null)) {
            $query->whereKey($validated['organizationId']);
        } elseif (filled($validated['organizationSlug'] ?? null)) {
            $query->where('slug', $validated['organizationSlug']);
        } else {
            return response()->json(['error' => __('ui.errors.no_organization')], 422);
        }

        $organization = $query->first();

        if (! $organization instanceof Organization || ! $organization->hasActiveMembership($user)) {
            return response()->json(['error' => __('ui.errors.not_a_member')], 403);
        }

        return response()->json([
            'organizationId' => $organization->getKey(),
            'organizationSlug' => $organization->slug,
            'ok' => true,
        ]);
    }

    public function desktopConfig(Request $request): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $plan = $organization->currentPlan();
        $proxy = $this->proxyEntitlements->forOrganization($organization);
        $agentControlEnabled = $this->planAllows($plan, Plan::FEATURE_AGENT_CONTROL);

        $config = [
            // Desktop capabilities follow the organization's plan so an admin
            // can package them commercially. Unknown/missing keys stay allowed
            // so plans created before a feature existed do not lock users out.
            'allowCustomProviders' => $this->customProvidersAllowed($organization, $plan),
            'allowZenModel' => true,
            'allowMultipleWorkspaces' => $this->planAllows($plan, Plan::FEATURE_MULTIPLE_WORKSPACES),
            'allowControlSettings' => true,
            'allowManageExtensions' => $this->planAllows($plan, Plan::FEATURE_MANAGE_EXTENSIONS),
            'allowBuiltInExtensions' => $this->planAllows($plan, Plan::FEATURE_MANAGE_EXTENSIONS),
            'allowAlphaUpdates' => true,
            'showWelcomePage' => true,
            'connectEnabled' => false,
            'dashboardEnabled' => false,
            'automationsEnabled' => false,
            // The desktop always requires sign-in on the public flavor. The
            // `granted` field only becomes restrictive once the operator
            // turns `accountRequiredForDesktopAccess` on: until then every plan
            // grants access, so a deployment that has not enabled the switch
            // behaves exactly as before the gate existed.
            'desktopAccess' => [
                'required' => true,
                'granted' => $this->desktopAccessGranted($organization, $plan),
                'planSlug' => $plan?->slug,
            ],
            // Admin edits to providers and model assignments must reach a
            // running desktop app without a relaunch. The desktop plugin polls
            // this flag and re-reads the provider catalog when it is true.
            'liveProviderSync' => true,
            'brandAppName' => (string) config('idexal.brand.name', 'idexal Os'),
            'brandAccentColor' => 'lime',
            'organizationId' => $organization->getKey(),
            // What agents may do on this member's computer. The desktop
            // already enforces these ordered rules; without this key it had
            // nothing to enforce, so "Ask first" and "Block" were inert. An
            // empty list is the default policy and changes nothing.
            'agentPermissions' => [
                'rules' => AgentPermissionPolicyModel::rulesFor($organization->getKey()),
            ],
            'proxy' => $proxy,
            // What the plan lets this member use, provider by provider, and
            // whether the "bring your own key" surface should be visible. The
            // gateway enforces the same gates when it builds a catalog; this
            // block lets the desktop show the decision instead of guessing.
            'providerEntitlements' => $this->providerEntitlements->forOrganization($organization),
            'agentControl' => [
                'enabled' => $agentControlEnabled,
                'settings' => $agentControlEnabled && $this->planAllows($plan, Plan::FEATURE_AGENT_SETTINGS),
                'proxies' => $agentControlEnabled
                    && $this->planAllows($plan, Plan::FEATURE_AGENT_PROXY)
                    && $this->planAllows($plan, Plan::FEATURE_PROXY)
                    && (bool) ($proxy['enabled'] ?? false),
                'extensions' => $agentControlEnabled
                    && $this->planAllows($plan, Plan::FEATURE_AGENT_EXTENSIONS)
                    && $this->planAllows($plan, Plan::FEATURE_MANAGE_EXTENSIONS),
                'browser' => $agentControlEnabled
                    && $this->planAllows($plan, Plan::FEATURE_AGENT_BROWSER)
                    && $this->planAllows($plan, Plan::FEATURE_MANAGE_EXTENSIONS),
                'providers' => $agentControlEnabled
                    && $this->planAllows($plan, Plan::FEATURE_AGENT_PROVIDERS)
                    && $this->planAllows($plan, Plan::FEATURE_CUSTOM_PROVIDERS),
            ],
        ];

        // Connector permission scopes stay off until an operator turns the
        // feature on in /admin/features. While off the desktop receives no
        // block at all, so every installed connector behaves exactly as it did
        // before this feature existed.
        if ($this->settings->boolean(DesktopSwitch::ConnectorPermissionScopes->settingKey())) {
            $config['connectorPermissions'] = ConnectorGrant::permissionsFor($organization->getKey(), $this->scopedConnectors());

            // A member's own open asks, so the desktop can show "approval
            // requested" on a connector they already asked for instead of
            // offering the button again. The scopes are the catalogue's, so a
            // member cannot ask for more than the marketplace declares.
            $config['connectorRequests'] = array_values(array_map(
                fn (ConnectorGrantRequest $ask): array => [
                    'slug' => (string) $ask->connector_slug,
                    'status' => (string) $ask->status,
                    'scopes' => array_values((array) $ask->scopes),
                    'requestedAt' => $ask->updated_at?->toIso8601String(),
                ],
                ConnectorGrantRequest::forMember($organization->getKey(), $this->user($request)->getKey()),
            ));
        }

        return response()->json($config);
    }

    /**
     * A member asks an operator to approve a connector. The ask is not a grant
     * — the operator still answers on the connectors screen — but it is what
     * puts the request in their queue, and asking again reopens the same ask
     * instead of piling up a second one.
     *
     * While the connector permission scopes feature is off this endpoint does
     * not exist, so a platform that has not switched the feature on behaves
     * exactly as it did before.
     */
    public function storeConnectorRequest(Request $request): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $user = $this->user($request);

        abort_unless($this->settings->boolean(DesktopSwitch::ConnectorPermissionScopes->settingKey()), 404);

        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:190'],
        ]);
        $slug = (string) $validated['slug'];
        $entry = $this->marketplace->item($slug);
        $requested = $entry !== null ? ConnectorGrantPolicy::requestedScopes($entry) : [];

        // Only a catalogue connector that actually asks for scopes is worth a
        // request; anything else is a bad slug, not a permission question.
        if ($entry === null || ($entry['type'] ?? null) !== 'connector' || $requested === []) {
            abort(404);
        }

        $grant = ConnectorGrant::bySlug($organization->getKey())[$slug] ?? null;

        // There is nothing to ask for when access is already granted.
        if ($grant instanceof ConnectorGrant
            && $grant->status === ConnectorGrant::STATUS_ACTIVE
            && ! $grant->isExpired()) {
            return response()->json([
                'error' => [
                    'message' => __('ui.admin.connectors.request_already_approved'),
                    'type' => 'permission_error',
                    'code' => 'already_approved',
                ],
            ], 409);
        }

        // A member who already has an open ask is not asking again: the row is
        // refreshed but operators are not notified twice for one request.
        $alreadyOpen = ConnectorGrantRequest::forMember($organization->getKey(), $user->getKey())[$slug] ?? null;
        $ask = ConnectorGrantRequest::ask($organization->getKey(), $slug, (string) $user->getKey(), $requested);

        if (! $alreadyOpen instanceof ConnectorGrantRequest) {
            $name = (string) ($entry['name'] ?? $slug);
            $this->notifications->notifyManagers(
                $organization,
                'connector_request',
                __('ui.admin.connectors.request_notification_title', ['connector' => $name]),
                __('ui.admin.connectors.request_notification_body', [
                    'member' => $user->name ?? (string) $user->email,
                    'connector' => $name,
                ]),
                route('admin.connectors', ['organization' => $organization->getKey()]),
            );

            AuditLog::record(
                'member.connector_request.created',
                $organization,
                $ask,
                __('ui.admin.connectors.request_audit', ['connector' => $name]),
                ['connector_grants' => [
                    $slug => ['from' => __('ui.admin.connectors.summary_none'), 'to' => __('ui.admin.connectors.summary_requested')],
                ]],
            );
        }

        return response()->json([
            'request' => [
                'slug' => (string) $ask->connector_slug,
                'status' => (string) $ask->status,
                'scopes' => array_values((array) $ask->scopes),
                'requestedAt' => $ask->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Every catalogue connector that asks for scopes. The desktop refuses one
     * of these until an admin approves it, and never trusts a client's claim
     * about which connectors those are.
     *
     * @return list<string>
     */
    private function scopedConnectors(): array
    {
        return collect($this->marketplace->catalog()['items'])
            ->filter(fn (array $entry): bool => ($entry['type'] ?? null) === 'connector'
                && ConnectorGrantPolicy::requestedScopes($entry) !== [])
            ->map(fn (array $entry): string => (string) $entry['slug'])
            ->values()
            ->all();
    }

    private function planAllows(?Plan $plan, string $feature): bool
    {
        if (! $plan instanceof Plan) {
            return true;
        }

        $features = is_array($plan->features) ? $plan->features : [];

        return ! array_key_exists($feature, $features) || (bool) $features[$feature];
    }

    /**
     * Whether a member of this organization may bring their own API keys.
     *
     * Two layers, both must allow:
     * - The organization's plan must not deny `custom_providers` (a plan
     *   created before the key existed still allows, matching `planAllows`).
     * - The operator-level `byok_killed` switch must be off. While it is on,
     *   no member of the organization may add their own keys, even if their
     *   plan's feature is on — this is the "force subscription-only" mode.
     *
     * A per-plan override is possible by setting the plan's `custom_providers`
     * feature explicitly to `true` while the org switch is on; the two are
     * OR-unioned so an admin can carve an exception for a specific plan.
     */
    private function customProvidersAllowed(Organization $organization, ?Plan $plan): bool
    {
        // The platform-wide switch outranks everything, and it is off by
        // default: a member's desktop offers the platform's own models only, so
        // nobody spends on a key the operator did not approve. Turning it on in
        // the provider console is what re-opens own-keys, and then a plan or a
        // workspace setting can narrow it further.
        if (! $this->settings->boolean('byok_enabled', false)) {
            return false;
        }

        $planAllows = $this->planAllows($plan, Plan::FEATURE_CUSTOM_PROVIDERS);
        $orgByokKilled = (bool) ($organization->settings['byok_killed'] ?? false);

        // The org kill switch hides the BYOK surface from every member unless
        // their plan explicitly opts back in (the per-plan override).
        if ($orgByokKilled && $planAllows) {
            $features = is_array($plan?->features) ? (array) $plan->features : [];
            $planExplicitlyGrants = array_key_exists(Plan::FEATURE_CUSTOM_PROVIDERS, $features)
                && (bool) $features[Plan::FEATURE_CUSTOM_PROVIDERS];

            return $planExplicitlyGrants;
        }

        return $planAllows;
    }

    /**
     * Whether a signed-in user on this organization's current plan may open
     * the desktop app at all.
     *
     * While the `accountRequiredForDesktopAccess` switch is off, every plan
     * grants access — the gate is invisible and a deployment that has not
     * adopted the feature behaves exactly as before. While the switch is on,
     * access follows the plan: a plan that has never been asked about the key
     * (created before the gate) still grants, and only a plan that has
     * explicitly set `desktop_access` to `false` denies.
     */
    private function desktopAccessGranted(Organization $organization, ?Plan $plan): bool
    {
        // An operator can require every account to pick a plan of its own. An
        // organization with no active subscription then gets nothing, so the
        // desktop holds at the plan chooser until one is chosen — the
        // "sign in, then subscribe" flow.
        if ($this->settings->boolean('feature_require_plan_selection', true)
            && $organization->currentSubscription() === null) {
            return false;
        }

        if (! $this->settings->boolean(DesktopSwitch::AccountRequiredForDesktopAccess->settingKey())) {
            return true;
        }

        if (! $plan instanceof Plan) {
            return false;
        }

        $features = is_array($plan->features) ? $plan->features : [];

        return ! array_key_exists(Plan::FEATURE_DESKTOP_ACCESS, $features) || (bool) $features[Plan::FEATURE_DESKTOP_ACCESS];
    }

    public function organization(Request $request): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $current = $this->context->membership();
        $user = $this->user($request);

        $members = Membership::query()
            ->with('user:id,name,email')
            ->where('organization_id', $organization->getKey())
            ->where('status', Membership::STATUS_ACTIVE)
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Membership $membership): bool => $membership->user instanceof User)
            ->map(fn (Membership $membership): array => [
                'id' => $membership->getKey(),
                'role' => $membership->role,
                'user' => [
                    'id' => $membership->user->getKey(),
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                ],
            ])
            ->values();

        return response()->json([
            'organization' => [
                'id' => $organization->getKey(),
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'currentMember' => [
                'id' => $current?->getKey(),
                'role' => $current?->role,
                'user' => $this->userPayload($user),
            ],
            'members' => $members->all(),
            'teams' => [],
            'features' => PlatformFeatures::map($this->settings),
        ]);
    }

    public function resources(Request $request): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $membership = $this->context->membership();
        $snapshot = $this->marketplaceSnapshot();

        return response()->json([
            'organizationId' => $organization->getKey(),
            'orgMemberId' => $membership?->getKey(),
            'teamIds' => [],
            'resources' => [
                'llmProviders' => new \stdClass,
                'marketplaces' => $snapshot['marketplaces'],
            ],
        ]);
    }

    public function library(): JsonResponse
    {
        $installed = $this->installedExtensions();

        return response()->json([
            'items' => $installed
                ->map(function (array $entry): array {
                    // The desktop shows what a plugin is made of on its row
                    // ("3 skills, 1 command"). That is the member list the
                    // catalogue already declares, grouped by member type, so it
                    // is derived here rather than left as an empty list the row
                    // would render as "no components".
                    $kinds = [];
                    foreach ((array) ($entry['includes'] ?? []) as $member) {
                        $memberEntry = $this->marketplace->item((string) $member);

                        if ($memberEntry === null) {
                            continue;
                        }

                        $kind = (string) ($memberEntry['type'] ?? '');
                        if ($kind !== '') {
                            $kinds[$kind] = true;
                        }
                    }

                    // A plugin with no `includes` is its own single member.
                    if ($kinds === [] && filled($entry['type'] ?? null)) {
                        $kinds[(string) $entry['type']] = true;
                    }

                    $componentKinds = array_keys($kinds);

                    return [
                        'type' => 'plugin',
                        'id' => 'plg_'.$entry['slug'],
                        'name' => (string) $entry['name'],
                        'description' => (string) ($entry['description'] ?? ''),
                        'componentKinds' => $componentKinds,
                        'componentCount' => count((array) ($entry['includes'] ?? [])) ?: 1,
                    ];
                })
                ->values()
                ->all(),
            'hasMore' => false,
            'total' => $installed->count(),
        ]);
    }

    /**
     * The plugins the signed-in member can use, in the shape the desktop's
     * Library reads. Access on this platform is organization-wide — an
     * installed plugin is available to every member of the workspace — so each
     * plugin reports one org-wide grant rather than a per-team list, and
     * `includeAccess` only decides whether that grant is included. Reporting
     * the truth here is what lets the Library's access view load instead of
     * failing on an endpoint the platform never served.
     */
    public function plugins(Request $request): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $includeAccess = $request->boolean('includeAccess');
        $bySlug = $includeAccess ? PluginAccessGrant::bySlug($organization->getKey()) : collect();

        $items = $this->installedExtensions()
            ->map(function (array $entry) use ($includeAccess, $bySlug): array {
                $item = [
                    'id' => 'plg_'.$entry['slug'],
                    'name' => (string) $entry['name'],
                    'description' => (string) ($entry['description'] ?? ''),
                    'status' => 'active',
                ];

                if ($includeAccess) {
                    $item['access'] = $this->accessPayload(
                        (string) $entry['slug'],
                        $bySlug->get((string) $entry['slug'], collect()),
                    );
                }

                return $item;
            })
            ->values()
            ->all();

        return response()->json([
            'items' => $items,
            'hasMore' => false,
            'total' => count($items),
        ]);
    }

    /**
     * One plugin's access list, in the shape the Library parses. A plugin that
     * is not installed for this organization is a 404, exactly as the client
     * expects.
     */
    public function pluginAccess(string $pluginId): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $slug = $this->pluginSlug($pluginId);

        $this->abortUnlessInstalled($slug);

        $grants = PluginAccessGrant::bySlug($organization->getKey())->get($slug, collect());
        $items = $this->accessPayload($slug, $grants);

        return response()->json([
            'items' => $items,
            'total' => count($items),
        ]);
    }

    /**
     * Grant a plugin to the whole workspace, or to one member of it.
     *
     * The desktop's target is one of `orgWide`, `orgMembershipId` or `teamId`.
     * Teams do not exist on this platform, so a team target is refused with a
     * plain reason instead of being stored as something it is not.
     */
    public function grantPluginAccess(Request $request, string $pluginId): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $slug = $this->pluginSlug($pluginId);

        $this->abortUnlessInstalled($slug);
        $this->abortUnlessManager();

        $validated = $request->validate([
            'orgWide' => ['nullable', 'boolean'],
            'orgMembershipId' => ['nullable', 'string', 'max:26'],
            'teamId' => ['nullable', 'string', 'max:26'],
            'role' => ['nullable', 'string', 'max:20'],
        ]);

        $role = in_array($validated['role'] ?? null, PluginAccessGrant::ROLES, true)
            ? (string) $validated['role']
            : PluginAccessGrant::DEFAULT_ROLE;

        $actorId = $request->user()?->getKey();

        if (filled($validated['teamId'] ?? null)) {
            return response()->json([
                'error' => [
                    'message' => __('ui.errors.teams_unsupported'),
                    'type' => 'invalid_request_error',
                    'code' => 'teams_unsupported',
                ],
            ], 422);
        }

        if (filled($validated['orgMembershipId'] ?? null)) {
            $membershipId = (string) $validated['orgMembershipId'];

            $membership = Membership::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($membershipId)
                ->first();

            if (! $membership instanceof Membership) {
                return response()->json([
                    'error' => [
                        'message' => __('ui.errors.not_found'),
                        'type' => 'invalid_request_error',
                        'code' => 'unknown_membership',
                    ],
                ], 404);
            }

            $grant = PluginAccessGrant::grantToMembership($organization->getKey(), $slug, $membershipId, $role, $actorId);
        } else {
            $grant = PluginAccessGrant::grantOrgWide($organization->getKey(), $slug, $role, $actorId);
        }

        AuditLog::record('admin.plugin_access.granted', $organization, $grant, $slug, [
            'org_wide' => (bool) $grant->org_wide,
            'role' => $grant->role,
        ]);

        return response()->json(['item' => $grant->toPayload()]);
    }

    /**
     * Revoke one grant. The plugin stays installed; the people it named simply
     * stop being entitled to it, which is what the Library's revoke means.
     */
    public function revokePluginAccess(string $pluginId, string $grantId): JsonResponse
    {
        $organization = $this->context->requireOrganization();

        $this->abortUnlessManager();

        $grant = PluginAccessGrant::query()
            ->where('organization_id', $organization->getKey())
            ->where('plugin_slug', $this->pluginSlug($pluginId))
            ->whereKey($grantId)
            ->first();

        abort_unless($grant instanceof PluginAccessGrant, 404);

        AuditLog::record('admin.plugin_access.revoked', $organization, $grant, (string) $grant->plugin_slug, [
            'org_wide' => (bool) $grant->org_wide,
        ]);

        $grant->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * The access list for one plugin. With no stored grants the plugin is
     * available to the whole workspace, so that is what is reported — an empty
     * list would read as "nobody has access".
     *
     * @param  Collection<int, PluginAccessGrant>  $grants
     * @return list<array<string, mixed>>
     */
    private function accessPayload(string $slug, Collection $grants): array
    {
        if ($grants->isEmpty()) {
            return [[
                'id' => 'grant_orgwide_'.$slug,
                'orgWide' => true,
                'orgMembershipId' => null,
                'teamId' => null,
                'role' => PluginAccessGrant::DEFAULT_ROLE,
            ]];
        }

        return $grants->map(fn (PluginAccessGrant $grant): array => $grant->toPayload())->values()->all();
    }

    private function pluginSlug(string $pluginId): string
    {
        return str_starts_with($pluginId, 'plg_') ? substr($pluginId, 4) : $pluginId;
    }

    private function abortUnlessInstalled(string $slug): void
    {
        $installed = $this->installedExtensions()->contains(fn (array $entry): bool => $entry['slug'] === $slug);

        abort_unless($installed, 404);
    }

    /**
     * Managing who may use a plugin is an operator action, the same gate the
     * workspace's API keys and desktop devices already use.
     */
    private function abortUnlessManager(): void
    {
        abort_unless($this->context->canManageApiKeys(), 403, __('ui.errors.not_allowed'));
    }

    public function mcpConnections(): JsonResponse
    {
        // Local and organization-owned MCP servers live in the desktop config.
        // The hosted platform delivers remote connectors as presets below and
        // keeps its own connection inventory empty rather than a 404 that
        // makes the whole Library Connectors tab look unavailable.
        return response()->json(['connections' => [], 'total' => 0]);
    }

    public function mcpConnectionPresets(): JsonResponse
    {
        $scopesEnabled = $this->settings->boolean(DesktopSwitch::ConnectorPermissionScopes->settingKey());

        $presets = collect($this->marketplace->catalog()['items'])
            ->filter(fn (array $entry): bool => ($entry['type'] ?? '') === 'connector'
                && filled($entry['url'] ?? null)
                && in_array($entry['authType'] ?? null, ['oauth', 'apikey', 'none'], true))
            ->map(function (array $entry) use ($scopesEnabled): array {
                $preset = [
                    'presetId' => 'preset_'.$entry['slug'],
                    'displayName' => (string) $entry['name'],
                    'description' => (string) ($entry['description'] ?? ''),
                    'url' => (string) $entry['url'],
                    'authType' => (string) $entry['authType'],
                ];

                // What the connector asks for, shown before an operator
                // approves anything. The approved set arrives separately, in
                // the desktop config, and only when the feature is on.
                if ($scopesEnabled) {
                    $preset['permissions'] = ConnectorGrantPolicy::requestedScopes($entry);
                }

                return $preset;
            })
            ->values()
            ->all();

        return response()->json(['presets' => $presets, 'total' => count($presets)]);
    }

    public function marketplaceCapabilities(): JsonResponse
    {
        $items = $this->installedSkillCapabilities();

        return response()->json([
            'items' => $items,
            'hasMore' => false,
            'total' => count($items),
        ]);
    }

    public function marketplaces(): JsonResponse
    {
        $catalog = $this->marketplace->catalog();

        return response()->json([
            'items' => [
                [
                    'id' => MarketplaceCatalogService::MARKETPLACE_ID,
                    'name' => (string) ($catalog['marketplace']['name'] ?? 'idexal Os Marketplace'),
                    'description' => (string) ($catalog['marketplace']['description'] ?? ''),
                    'status' => 'active',
                    'pluginCount' => count($catalog['items']),
                    'updatedAt' => $this->marketplaceUpdatedAt(),
                ],
            ],
        ]);
    }

    public function marketplaceResolved(string $marketplaceId): JsonResponse
    {
        abort_unless($marketplaceId === MarketplaceCatalogService::MARKETPLACE_ID, 404);

        $catalog = $this->marketplace->catalog();
        $updatedAt = $this->marketplaceUpdatedAt();

        return response()->json([
            'item' => [
                'marketplace' => [
                    'id' => MarketplaceCatalogService::MARKETPLACE_ID,
                    'name' => (string) ($catalog['marketplace']['name'] ?? 'idexal Os Marketplace'),
                    'description' => (string) ($catalog['marketplace']['description'] ?? ''),
                    'status' => 'active',
                    'pluginCount' => count($catalog['items']),
                    'updatedAt' => $updatedAt,
                ],
                'plugins' => collect($catalog['items'])
                    ->map(fn (array $entry): array => $this->pluginPayload($entry, $updatedAt))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function pluginResolved(string $pluginId): JsonResponse
    {
        $slug = str_starts_with($pluginId, 'plg_') ? substr($pluginId, 4) : $pluginId;
        $entry = $this->marketplace->item($slug);

        abort_if($entry === null, 404);

        return response()->json(['items' => $this->pluginResolvedItems($entry)]);
    }

    /**
     * The batched form the Library actually calls: one request resolves a page
     * of plugins instead of one request per plugin. A plugin that is not in the
     * catalogue is reported under `failures` for its own id — the same 404 its
     * single-plugin request would have been — so one missing plugin never fails
     * the batch.
     */
    public function resolvedPlugins(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('pluginIds', '')))
            ->map(fn (string $id): string => trim($id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->take(100)
            ->values();

        abort_if($ids->isEmpty(), 422);

        $plugins = [];
        $failures = [];

        foreach ($ids as $id) {
            $slug = str_starts_with($id, 'plg_') ? substr($id, 4) : $id;
            $entry = $this->marketplace->item($slug);

            if ($entry === null) {
                $failures[$id] = [
                    'status' => 404,
                    'error' => 'plugin_not_found',
                    'message' => __('ui.errors.not_found'),
                ];

                continue;
            }

            $plugins[$id] = ['items' => $this->pluginResolvedItems($entry)];
        }

        return response()->json([
            'plugins' => (object) $plugins,
            'failures' => (object) $failures,
        ]);
    }

    /**
     * One catalogue plugin's configuration members, shared by the single and
     * batched resolvers so the two can never disagree.
     *
     * @param  array<string, mixed>  $entry
     * @return list<array<string, mixed>>
     */
    private function pluginResolvedItems(array $entry): array
    {
        $updatedAt = $this->marketplaceUpdatedAt();
        $slug = (string) $entry['slug'];
        $members = filled($entry['includes'] ?? null) ? (array) $entry['includes'] : [$slug];
        $pluginRowId = 'plg_'.$slug;

        $items = [];
        foreach ($members as $member) {
            $memberEntry = $this->marketplace->item((string) $member);

            if ($memberEntry === null) {
                continue;
            }

            $items[] = [
                'id' => 'mem_'.$memberEntry['slug'],
                'pluginId' => $pluginRowId,
                'configObjectId' => 'co_'.$memberEntry['slug'],
                'configObject' => $this->configObject($memberEntry, $updatedAt),
            ];
        }

        return $items;
    }

    public function inventoryRevision(): JsonResponse
    {
        $organization = $this->context->requireOrganization();

        $providerStamp = (string) (AiProvider::query()->max('updated_at') ?? '');
        $modelStamp = (string) (AiModel::query()->max('updated_at') ?? '');

        return response()->json([
            'revision' => md5($this->marketplace->revision().'|'.$providerStamp.'|'.$modelStamp.'|'.DesktopSync::revision().'|'.$organization->getKey()),
        ]);
    }

    /**
     * Installed extensions for the signed-in member, enriched with catalog
     * fields. Rows shared by the organization count for every member.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function installedExtensions()
    {
        $organization = $this->context->requireOrganization();
        $membership = $this->context->membership();
        $user = $this->user(request());

        $decisions = PluginAccessGrant::decisionsFor(
            PluginAccessGrant::bySlug($organization->getKey()),
            $membership?->getKey() !== null ? (string) $membership->getKey() : null,
        );

        return $this->marketplace->installedFor(
            (string) $user->id,
            $membership?->organization_id !== null ? (string) $membership->organization_id : null,
        )
            // An installed plugin the member is not entitled to is not listed:
            // that is what a grant means, and what the Library's access view
            // writes. A plugin with no grants at all names nobody, so it stays
            // available to the whole workspace.
            ->reject(fn (ExtensionInstallation $row): bool => array_key_exists((string) $row->extension_name, $decisions['known'])
                && ($decisions['allowed'][(string) $row->extension_name] ?? false) === false)
            ->map(function (ExtensionInstallation $row): array {
                // extension_name stores the catalog slug: the stable identity
                // every consumer (library, capabilities, uninstall) keys on.
                $slug = (string) $row->extension_name;
                $entry = $this->marketplace->item($slug);

                return [
                    'slug' => $slug,
                    'name' => (string) ($entry['name'] ?? $slug),
                    'description' => (string) ($entry['description'] ?? ''),
                    'type' => $row->extension_type,
                    'includes' => (array) ($entry['includes'] ?? []),
                ];
            })
            ->values();
    }

    /**
     * Marketplace capability rows for every installed skill, in the exact
     * shape the desktop parser accepts (objectType must be one of the modeled
     * types: skill, agent, command, tool, mcp, hook, context, custom, workflow).
     *
     * @return list<array{configObjectId: string, marketplaceId: string, objectType: string, pluginId: string}>
     */
    private function installedSkillCapabilities(): array
    {
        $items = [];

        foreach ($this->installedExtensions() as $entry) {
            $members = filled($entry['includes']) ? (array) $entry['includes'] : [$entry['slug']];

            foreach ($members as $member) {
                $memberEntry = $this->marketplace->item((string) $member);

                if ($memberEntry === null) {
                    continue;
                }

                $items[] = [
                    'configObjectId' => 'co_'.$memberEntry['slug'],
                    'marketplaceId' => MarketplaceCatalogService::MARKETPLACE_ID,
                    'objectType' => 'skill',
                    'pluginId' => 'plg_'.$entry['slug'],
                ];
            }
        }

        return $items;
    }

    /**
     * Resource snapshot for the marketplaces the org is subscribed to.
     *
     * @return array<string, mixed>
     */
    private function marketplaceSnapshot(): array
    {
        $updatedAt = $this->marketplaceUpdatedAt();
        $plugins = [];

        foreach ($this->installedExtensions() as $entry) {
            $members = filled($entry['includes']) ? (array) $entry['includes'] : [$entry['slug']];
            $configItems = [];

            foreach ($members as $member) {
                $configItems['co_'.$member] = ['lastUpdatedAt' => $updatedAt];
            }

            $plugins['plg_'.$entry['slug']] = [
                'lastUpdatedAt' => $updatedAt,
                'configItems' => $configItems,
            ];
        }

        return [
            'marketplaces' => [
                MarketplaceCatalogService::MARKETPLACE_ID => [
                    'lastUpdatedAt' => $updatedAt,
                    'plugins' => $plugins,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function pluginPayload(array $entry, string $updatedAt): array
    {
        $members = filled($entry['includes'] ?? null) ? (array) $entry['includes'] : [$entry['slug']];

        return [
            'id' => 'plg_'.$entry['slug'],
            'name' => (string) $entry['name'],
            'description' => (string) ($entry['description'] ?? ''),
            'status' => 'active',
            'memberCount' => 0,
            'updatedAt' => $updatedAt,
            'componentCounts' => [
                'skill' => $entry['type'] === 'skill' ? 1 : count($members),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function configObject(array $entry, string $updatedAt): array
    {
        $objectType = match ($entry['type'] ?? '') {
            'connector' => 'mcp',
            'skill' => 'skill',
            'plugin' => 'custom',
            default => 'custom',
        };

        $config = [
            'id' => 'co_'.$entry['slug'],
            'objectType' => $objectType,
            'title' => (string) $entry['name'],
            'description' => (string) ($entry['description'] ?? ''),
            'status' => 'active',
            'updatedAt' => $updatedAt,
        ];

        $rawSource = $objectType === 'skill' ? $this->skillSource((string) $entry['slug']) : null;

        $config['latestVersion'] = [
            'id' => 'co_'.$entry['slug'].'_'.$entry['version'],
            ...(is_string($rawSource) && $rawSource !== '' ? ['rawSourceText' => $rawSource] : []),
            'createdAt' => $updatedAt,
        ];

        return $config;
    }

    /** The bundled SKILL.md content, delivered so the desktop can materialize the skill offline. */
    private function skillSource(string $slug): ?string
    {
        $path = resource_path('marketplace/skills/'.$slug.'.md');

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    private function marketplaceUpdatedAt(): string
    {
        return now()->toIso8601String();
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @return array{id: string, email: string, name: string|null}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'email' => (string) $user->email,
            'name' => $user->name,
        ];
    }
}
