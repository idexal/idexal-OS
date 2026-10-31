<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\Plan;
use App\Services\ComboModelService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The public "what you get" page: the AI providers and models this platform
 * can offer, grouped by the plan that unlocks them.
 *
 * It reads the same rows the gateway serves, so it cannot advertise a provider
 * the platform does not actually run. A provider a plan does not cover is
 * still shown, but under the plan it belongs to — a provider assigned to no
 * plan is offered to every plan, so it is listed as available on all of them.
 */
class ProvidersController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function index(): View
    {
        $providers = AiProvider::query()
            ->with(['models' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('hybrid_config')
                ->orderBy('sort_order')
                ->orderBy('display_name')])
            ->where('is_active', true)
            ->where('slug', '!=', ComboModelService::PROVIDER_SLUG)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get();

        return view('public.providers', [
            'groups' => $this->groups($providers, $plans),
            'providerCount' => $providers->count(),
            'modelCount' => $providers->sum(fn (AiProvider $provider): int => $provider->models->count()),
        ]);
    }

    /**
     * One entry per provider: the provider itself plus the names of the plans
     * that can use it. A provider with no plan rows is available on every
     * public plan, which is the platform's existing rule.
     *
     * @param  Collection<int, AiProvider>  $providers
     * @param  Collection<int, Plan>  $plans
     * @return list<array<string, mixed>>
     */
    private function groups($providers, $plans): array
    {
        $planNames = $plans->mapWithKeys(fn (Plan $plan): array => [$plan->getKey() => $plan->name]);

        return $providers
            ->map(function (AiProvider $provider) use ($plans): array {
                $assigned = $provider->plans()->pluck('plans.id')->all();

                $availableOn = $assigned === []
                    ? $plans->map(fn (Plan $plan): string => (string) $plan->name)->all()
                    : $plans
                        ->filter(fn (Plan $plan): bool => in_array($plan->getKey(), $assigned, true))
                        ->map(fn (Plan $plan): string => (string) $plan->name)
                        ->values()
                        ->all();

                return [
                    'slug' => (string) $provider->slug,
                    'name' => (string) $provider->name,
                    'driver' => (string) $provider->driver,
                    'streaming' => (bool) $provider->supports_streaming,
                    'availableOn' => $availableOn,
                    'models' => $provider->models
                        ->map(fn ($model): array => [
                            'id' => (string) $model->model_id,
                            'name' => (string) $model->display_name,
                            'contextWindow' => $model->context_window,
                            'contextLabel' => $this->tokens($model->context_window),
                            'maxOutputLabel' => $this->tokens($model->max_output_tokens),
                            'inputPrice' => $this->price($model->input_price),
                            'outputPrice' => $this->price($model->output_price),
                            'capabilities' => $this->capabilities($model->capabilities),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $group): bool => $group['models'] !== [])
            ->values()
            ->all();
    }

    /** A token count a reader can compare: 1.05M, 128K, 8192. */
    private function tokens(mixed $value): ?string
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        $count = (int) $value;

        if ($count >= 1_000_000) {
            return rtrim(rtrim(number_format($count / 1_000_000, 2, '.', ''), '0'), '.').'M';
        }

        if ($count >= 1_000) {
            return rtrim(rtrim(number_format($count / 1_000, 1, '.', ''), '0'), '.').'K';
        }

        return (string) $count;
    }

    /** The published price per million tokens, or null when the plan hides it. */
    private function price(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;

        return '$'.rtrim(rtrim(number_format($amount, 4, '.', ''), '0'), '.');
    }

    /**
     * The capabilities a person chooses a model by, in plain words. "chat" is
     * every model's baseline and says nothing, so it is left out.
     *
     * @return list<string>
     */
    private function capabilities(mixed $value): array
    {
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $labels = [
            'tools' => 'ui.providers.cap_tools',
            'function_calling' => 'ui.providers.cap_tools',
            'vision' => 'ui.providers.cap_vision',
            'image_input' => 'ui.providers.cap_vision',
            'reasoning' => 'ui.providers.cap_reasoning',
            'audio' => 'ui.providers.cap_audio',
            'embeddings' => 'ui.providers.cap_embeddings',
            'streaming' => 'ui.providers.cap_streaming',
        ];

        $found = [];

        foreach ($decoded as $capability => $enabled) {
            $key = Str::lower((string) $capability);

            if ($enabled === true && isset($labels[$key])) {
                $found[] = $labels[$key];
            }
        }

        return array_values(array_unique($found));
    }
}
