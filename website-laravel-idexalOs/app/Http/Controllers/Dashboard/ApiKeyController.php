<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\NotificationService;
use App\Services\OrganizationContext;
use App\Services\WebhookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiKeyController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly NotificationService $notifications,
        private readonly WebhookService $webhooks,
    ) {}

    public function index(): View
    {
        $organization = $this->context->requireOrganization();
        $plan = $organization->currentPlan();
        $limit = $plan?->limit(Plan::LIMIT_API_KEYS);
        $keys = $organization->apiKeys()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->get();

        return view('dashboard.api-keys.index', [
            'organization' => $organization,
            'keys' => $keys,
            'plan' => $plan,
            'limit' => $limit === null ? null : (int) $limit,
            'canManage' => $this->context->canManageApiKeys(),
            'newKey' => session('new_api_key'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->context->requireOrganization();

        if (! $this->context->canManageApiKeys()) {
            abort(403, __('ui.errors.not_allowed'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in([
                ApiKey::ABILITY_GATEWAY_CHAT,
                ApiKey::ABILITY_GATEWAY_MODELS,
                ApiKey::ABILITY_DESKTOP,
            ])],
        ]);

        $plan = $organization->currentPlan();
        $limit = $plan?->limit(Plan::LIMIT_API_KEYS);

        if ($limit !== null && $organization->apiKeys()->whereNull('revoked_at')->count() >= (int) $limit) {
            return back()->withErrors([
                'name' => __('ui.api_keys.limit_reached', ['limit' => $limit]),
            ])->withInput();
        }

        [$key, $plain] = ApiKey::issue(
            $organization,
            $request->user(),
            $validated['name'],
            array_values($validated['abilities']),
        );

        AuditLog::record('api_key.created', $organization, $key, $key->name);
        $this->notifications->send(
            $request->user(),
            'api_key.created',
            __('ui.notifications.api_key_created_title'),
            __('ui.notifications.api_key_created_body', ['name' => $key->name]),
            route('dashboard.api-keys.index'),
            $organization,
        );
        $this->webhooks->dispatchEvent('api_key.created', $organization, [
            'id' => $key->getKey(),
            'name' => $key->name,
            'prefix' => $key->key_prefix,
            'abilities' => $key->abilities,
        ]);

        return redirect()->route('dashboard.api-keys.index')->with([
            'status' => __('ui.common.saved'),
            'new_api_key' => $plain,
        ]);
    }

    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $organization = $this->context->requireOrganization();

        if (! $this->context->canManageApiKeys()) {
            abort(403, __('ui.errors.not_allowed'));
        }

        abort_unless($apiKey->organization_id === $organization->getKey(), 404);

        $apiKey->revoke();
        AuditLog::record('api_key.revoked', $organization, $apiKey, $apiKey->name);

        return redirect()->route('dashboard.api-keys.index')->with('status', __('ui.common.deleted'));
    }
}
