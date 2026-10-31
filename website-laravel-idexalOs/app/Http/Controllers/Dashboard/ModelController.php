<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\ComboModelService;
use App\Services\GatewayService;
use App\Services\OrganizationContext;
use Illuminate\Contracts\View\View;

/**
 * The models a managed desktop may use in this workspace.
 *
 * The desktop opens this page as "Manage organization models" at
 * `/dashboard/custom-llm-providers`, a den-web address. The self-hosted platform
 * must answer it too, or the desktop's own button lands on a 404. This page is
 * the single destination for that address and its older aliases.
 *
 * The catalog query stays the single authority on visibility: the page reads
 * `GatewayService::desktopGatewayModels()`, the same list the desktop receives,
 * so it can never advertise a model the app cannot actually reach.
 */
class ModelController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly GatewayService $gateway,
        private readonly ComboModelService $combos,
    ) {}

    public function index(): View
    {
        $organization = $this->context->requireOrganization();

        $models = $this->gateway->desktopGatewayModels($organization)
            ->map(fn (AiModel $model): array => $this->row($model))
            ->all();

        return view('dashboard.models', [
            'organization' => $organization,
            'models' => $models,
        ]);
    }

    /**
     * @return array{name: string, model_id: string, provider: string, provider_slug: string, driver: string, context: int|null, is_combo: bool, members: int}
     */
    private function row(AiModel $model): array
    {
        $provider = $model->provider;
        $isCombo = $this->combos->isCombo($model);

        return [
            'name' => (string) ($model->display_name ?: $model->model_id),
            'model_id' => (string) $model->model_id,
            'provider' => $provider instanceof AiProvider ? (string) $provider->name : '—',
            'provider_slug' => $provider instanceof AiProvider ? (string) $provider->slug : '—',
            'driver' => $provider instanceof AiProvider
                ? (string) (AiProvider::drivers()[$provider->driver] ?? $provider->driver)
                : '—',
            'context' => $model->context_window !== null ? (int) $model->context_window : null,
            'is_combo' => $isCombo,
            'members' => $isCombo ? count($this->combos->memberIds($model)) : 0,
        ];
    }
}
