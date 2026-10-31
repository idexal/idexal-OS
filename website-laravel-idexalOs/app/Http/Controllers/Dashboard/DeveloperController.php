<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AuditLog;
use App\Models\GatewayRequest;
use App\Models\WebhookEndpoint;
use App\Services\OrganizationContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeveloperController extends Controller
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function index(Request $request): View
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeDeveloper();

        $status = $request->integer('status');
        $model = (string) $request->query('model', '');

        $requests = GatewayRequest::query()
            ->with(['provider:id,name,slug', 'aiModel:id,display_name,model_id'])
            ->where('organization_id', $organization->getKey())
            ->when($status > 0, fn ($query) => $query->where('status_code', $status))
            ->when($model !== '', fn ($query) => $query->where('model', 'like', '%'.$model.'%'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.developer.index', [
            'organization' => $organization,
            'requests' => $requests,
            'webhooks' => $organization->webhookEndpoints()->withCount('deliveries')->latest('created_at')->get(),
            'models' => AiModel::query()->where('is_active', true)->orderBy('display_name')->get(),
            'eventOptions' => $this->eventOptions(),
            'newSecret' => session('webhook_secret'),
            'filterStatus' => $status,
            'filterModel' => $model,
        ]);
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeDeveloper();

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_merge(['*'], array_keys($this->eventOptions())))],
        ]);

        $secret = WebhookEndpoint::generateSecret();
        $endpoint = $organization->webhookEndpoints()->create([
            'url' => $validated['url'],
            'secret' => $secret,
            'events' => array_values(array_unique($validated['events'])),
            'is_active' => true,
        ]);

        AuditLog::record('webhook.created', $organization, $endpoint, $endpoint->url);

        return redirect()->route('dashboard.developer')->with([
            'status' => __('ui.common.saved'),
            'webhook_secret' => $secret,
        ]);
    }

    public function updateWebhook(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeDeveloper();
        abort_unless($webhookEndpoint->organization_id === $organization->getKey(), 404);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_merge(['*'], array_keys($this->eventOptions())))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $webhookEndpoint->forceFill([
            'url' => $validated['url'],
            'events' => array_values(array_unique($validated['events'])),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        AuditLog::record('webhook.updated', $organization, $webhookEndpoint, $webhookEndpoint->url);

        return redirect()->route('dashboard.developer')->with('status', __('ui.common.saved'));
    }

    public function rotateWebhook(WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeDeveloper();
        abort_unless($webhookEndpoint->organization_id === $organization->getKey(), 404);

        $secret = WebhookEndpoint::generateSecret();
        $webhookEndpoint->forceFill(['secret' => $secret])->save();
        AuditLog::record('webhook.secret_rotated', $organization, $webhookEndpoint, $webhookEndpoint->url);

        return redirect()->route('dashboard.developer')->with([
            'status' => __('ui.common.saved'),
            'webhook_secret' => $secret,
        ]);
    }

    public function destroyWebhook(WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeDeveloper();
        abort_unless($webhookEndpoint->organization_id === $organization->getKey(), 404);

        $webhookEndpoint->delete();
        AuditLog::record('webhook.deleted', $organization);

        return redirect()->route('dashboard.developer')->with('status', __('ui.common.deleted'));
    }

    public function deliveries(WebhookEndpoint $webhookEndpoint): View
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeDeveloper();
        abort_unless($webhookEndpoint->organization_id === $organization->getKey(), 404);

        return view('dashboard.developer.deliveries', [
            'organization' => $organization,
            'webhook' => $webhookEndpoint,
            'deliveries' => $webhookEndpoint->deliveries()->latest('created_at')->paginate(25),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function eventOptions(): array
    {
        $events = [
            'invoice.paid',
            'subscription.updated',
            'api_key.created',
            'desktop.connected',
            'usage.threshold',
        ];
        $labels = __('ui.developer.webhook_events_list');

        return collect($events)
            ->mapWithKeys(fn (string $event): array => [
                $event => is_array($labels) ? (string) ($labels[$event] ?? $event) : $event,
            ])
            ->all();
    }

    private function authorizeDeveloper(): void
    {
        if (! $this->context->canManageApiKeys()) {
            abort(403, __('ui.errors.not_allowed'));
        }
    }
}
