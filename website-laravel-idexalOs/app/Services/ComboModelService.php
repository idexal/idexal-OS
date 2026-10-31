<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Groups equivalent models from different providers into one public model.
 * Members keep their own provider, prices and credentials; the gateway tries
 * them in rotation and falls back to the rest when a provider fails or its
 * quota is exhausted.
 *
 * A combo carries no entitlement of its own: `plan_ai_model` rows are never
 * written for it. It is offered when at least one member is entitled, and it
 * hides those members only while it is offered. Storing a copy of the members'
 * plans on the combo is what made a fresh workspace see an empty catalog: the
 * copy went stale the moment an operator edited a plan.
 */
class ComboModelService
{
    public const PROVIDER_SLUG = 'idexal-combo';

    public const PROVIDER_NAME = 'idexal Os Combo';

    /**
     * Rebuild automatic combos from active models that share a model id.
     * Manual combos (auto=false) are never touched.
     */
    public function syncAutomaticCombos(): int
    {
        $provider = $this->systemProvider();

        $models = AiModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', fn (Builder $query) => $query
                ->where('is_active', true)
                ->whereNull('organization_id')
                ->where('slug', '!=', self::PROVIDER_SLUG))
            ->get();

        $groups = $models->groupBy(fn (AiModel $model): string => $this->familyKey((string) $model->model_id));
        $kept = [];

        foreach ($groups as $familyKey => $members) {
            if ($members->count() < 2) {
                continue;
            }

            $members = $members
                ->sortByDesc(fn (AiModel $model): int => (int) ($model->provider?->priority ?? 0))
                ->values();

            $comboModelId = mb_substr('combo-'.Str::slug($familyKey), 0, 190);
            $first = $members->first();
            $capabilities = $this->capabilityUnion($members);
            $displayName = trim((string) ($first->display_name ?: $first->model_id));
            if (! str_ends_with(mb_strtolower($displayName), ' combo')) {
                $displayName .= ' Combo';
            }

            $config = [
                'strategy' => 'round_robin',
                'auto' => true,
                'family' => $familyKey,
                'members' => $members->map(fn (AiModel $model): array => [
                    'ai_model_id' => $model->getKey(),
                    'role' => $this->roleFor($model),
                    'priority' => (int) ($model->provider?->priority ?? 0),
                ])->values()->all(),
            ];

            $combo = AiModel::query()->updateOrCreate(
                ['ai_provider_id' => $provider->getKey(), 'model_id' => $comboModelId],
                [
                    'display_name' => $displayName,
                    'description' => 'Automatic combo across '.$members->count().' providers. Rotates on quota and failure.',
                    'context_window' => $members->max('context_window'),
                    'max_output_tokens' => $members->max('max_output_tokens'),
                    'capabilities' => $capabilities,
                    'reasoning_options' => $this->reasoningOptionsUnion($members),
                    'hybrid_config' => $config,
                    'is_active' => true,
                    'sort_order' => (int) $members->min('sort_order'),
                ],
            );

            $kept[] = $combo->getKey();
        }

        AiModel::query()
            ->where('ai_provider_id', $provider->getKey())
            ->whereNotIn('id', $kept)
            ->get()
            ->each(function (AiModel $combo): void {
                if (($combo->hybrid_config['auto'] ?? false) === true) {
                    $combo->forceFill(['is_active' => false])->save();
                }
            });

        return count($kept);
    }

    public function systemProvider(): AiProvider
    {
        return AiProvider::query()->firstOrCreate(
            ['slug' => self::PROVIDER_SLUG],
            [
                'name' => self::PROVIDER_NAME,
                'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
                'base_url' => 'https://combo.invalid/v1',
                'is_active' => true,
                'priority' => 0,
                'supports_streaming' => true,
            ],
        );
    }

    public function isCombo(AiModel $model): bool
    {
        return $model->provider?->slug === self::PROVIDER_SLUG
            || is_array($model->hybrid_config);
    }

    /**
     * The identity two provider rows must share to become one combo. Vendor
     * prefixes, separator style and a trailing build stamp are naming, not a
     * different model. Size and variant tokens are identity: `deepseek-v4` must
     * never absorb `deepseek-v4-flash`.
     */
    public function familyKey(string $modelId): string
    {
        $key = mb_strtolower(trim($modelId));

        if (str_contains($key, '/')) {
            $key = substr($key, (int) strrpos($key, '/') + 1);
        }

        $key = trim((string) preg_replace('/[^a-z0-9]+/u', '-', $key), '-');
        $withoutBuild = (string) preg_replace('/-(?:19|20)?\d{4,8}$/u', '', $key);

        return $withoutBuild !== '' ? $withoutBuild : $key;
    }

    /**
     * The member model ids a combo stands for.
     *
     * @return list<string>
     */
    public function memberIds(AiModel $combo): array
    {
        return collect($combo->hybrid_config['members'] ?? [])
            ->pluck('ai_model_id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
    }

    /**
     * The member model ids a set of combos replaces in a catalog. Only the
     * combos actually offered to an organization hide their members: a combo
     * nobody can run must never take its members down with it.
     *
     * @param  Collection<int, AiModel>  $combos
     * @return list<string>
     */
    public function hiddenMemberIds(Collection $combos): array
    {
        return $combos
            ->flatMap(fn (AiModel $combo): array => $this->memberIds($combo))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve a combo to eligible member models in attempt order.
     *
     * @param  array<string, mixed>  $input
     * @return Collection<int, AiModel>
     */
    public function expand(AiModel $combo, Organization $organization, array $input, string $endpoint): Collection
    {
        $config = is_array($combo->hybrid_config) ? $combo->hybrid_config : [];
        $memberIds = $this->memberIds($combo);

        if ($memberIds === []) {
            return collect();
        }

        $drivers = $endpoint === 'messages'
            ? [AiProvider::DRIVER_ANTHROPIC]
            : [AiProvider::DRIVER_OPENAI, AiProvider::DRIVER_OPENAI_COMPATIBLE];
        $plan = $organization->currentPlan();

        $eligible = AiModel::query()
            ->with('provider')
            ->whereIn('id', $memberIds)
            ->where('is_active', true)
            ->whereHas('provider', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('slug', '!=', self::PROVIDER_SLUG)
                ->whereIn('driver', $drivers)
                ->where(fn (Builder $query) => $query
                    ->whereNull('organization_id')
                    ->orWhere('organization_id', $organization->getKey()))
                ->where(function (Builder $query) use ($plan): void {
                    $query->whereDoesntHave('plans');
                    if ($plan !== null) {
                        $query->orWhereHas('plans', fn (Builder $plans) => $plans->whereKey($plan->getKey()));
                    }
                }))
            ->where(function (Builder $query) use ($plan): void {
                $query->whereDoesntHave('plans');
                if ($plan !== null) {
                    $query->orWhereHas('plans', fn (Builder $plans) => $plans->whereKey($plan->getKey()));
                }
            })
            ->get()
            ->filter(fn (AiModel $model): bool => $model->provider instanceof AiProvider
                && $this->providerHasCredential($model->provider));

        if ($eligible->isEmpty()) {
            return collect();
        }

        $priorityOrder = collect($config['members'] ?? [])
            ->keyBy(fn (array $member): string => (string) ($member['ai_model_id'] ?? ''))
            ->all();

        // A manual combo stores an attempt order per member, so "primary, then
        // backup" is exactly what the operator typed. Configs written before
        // that field existed (automatic combos included) keep ranking by the
        // provider priority they were built from.
        $hasExplicitOrder = collect($priorityOrder)->isNotEmpty()
            && collect($priorityOrder)->every(fn (array $member): bool => array_key_exists('order', $member));

        $sorted = $hasExplicitOrder
            ? $eligible->sortBy(fn (AiModel $model): int => (int) ($priorityOrder[$model->getKey()]['order'] ?? PHP_INT_MAX))->values()
            : $eligible->sortByDesc(fn (AiModel $model): int => (int) ($priorityOrder[$model->getKey()]['priority'] ?? $model->provider?->priority ?? 0))->values();

        $strategy = (string) ($config['strategy'] ?? 'round_robin');
        $role = $this->inferRole($input);

        if ($strategy === 'role') {
            $matches = $sorted->filter(fn (AiModel $model): bool => $this->memberRole($model, $priorityOrder) === $role)->values();
            $others = $sorted->reject(fn (AiModel $model): bool => $this->memberRole($model, $priorityOrder) === $role)->values();
            $sorted = $matches->isNotEmpty() ? $matches->merge($others)->values() : $sorted;
        }

        if ($strategy === 'weighted' && $sorted->count() > 1) {
            $sorted = $this->weightedOrder($sorted, $priorityOrder, $combo);
        } elseif (in_array($strategy, ['round_robin', 'role'], true) && $sorted->count() > 1) {
            $offset = (int) Cache::increment('combo_rr:'.$combo->getKey()) % $sorted->count();
            if ($offset > 0) {
                $sorted = $sorted->slice($offset)->merge($sorted->take($offset))->values();
            }
        }

        return $sorted;
    }

    /**
     * Weighted rotation: a member with weight 3 fills three of every (sum of
     * weights) slots, so it answers proportionally more often while every other
     * member stays reachable as fallback. Weight 0 means "fallback only".
     *
     * @param  Collection<int, AiModel>  $members
     * @param  array<string, array<string, mixed>>  $priorityOrder
     * @return Collection<int, AiModel>
     */
    private function weightedOrder(Collection $members, array $priorityOrder, AiModel $combo): Collection
    {
        $slots = [];

        foreach ($members as $model) {
            $member = $priorityOrder[$model->getKey()] ?? [];
            $weight = max(0, min((int) ($member['weight'] ?? $member['priority'] ?? 1), 100));

            for ($slot = 0; $slot < $weight; $slot++) {
                $slots[] = $model->getKey();
            }
        }

        if ($slots === []) {
            return $members;
        }

        $offset = (int) Cache::increment('combo_weighted:'.$combo->getKey()) % count($slots);
        $rotated = array_merge(array_slice($slots, $offset), array_slice($slots, 0, $offset));

        $order = [];
        foreach ($rotated as $key) {
            if (! in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        return $members
            ->sortBy(function (AiModel $model) use ($order): int {
                $index = array_search($model->getKey(), $order, true);

                return $index === false ? PHP_INT_MAX : (int) $index;
            })
            ->values();
    }

    /**
     * @param  Collection<int, AiModel>  $members
     * @return array<string, bool>
     */
    private function capabilityUnion(Collection $members): array
    {
        $capabilities = [];

        foreach ($members as $model) {
            foreach ((array) ($model->capabilities ?? []) as $capability => $enabled) {
                if ($enabled === true) {
                    $capabilities[$capability] = true;
                }
            }
        }

        return $capabilities;
    }

    /**
     * @param  Collection<int, AiModel>  $members
     * @return list<string>|null
     */
    private function reasoningOptionsUnion(Collection $members): ?array
    {
        $options = $members->flatMap(fn (AiModel $model): array => is_array($model->reasoning_options) ? $model->reasoning_options : [])
            ->unique()
            ->values()
            ->all();

        return $options !== [] ? $options : null;
    }

    private function roleFor(AiModel $model): string
    {
        if ($model->supports('image')) {
            return 'image';
        }
        if ($model->supports('search')) {
            return 'search';
        }
        if ($model->supports('tools')) {
            return 'coding';
        }
        if ($model->supports('reasoning')) {
            return 'reasoning';
        }
        if ($model->supports('vision')) {
            return 'vision';
        }

        return 'general';
    }

    /**
     * @param  array<string, array<string, mixed>>  $priorityOrder
     */
    private function memberRole(AiModel $model, array $priorityOrder): string
    {
        $role = $priorityOrder[$model->getKey()]['role'] ?? null;

        return is_string($role) && $role !== '' ? $role : $this->roleFor($model);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function inferRole(array $input): string
    {
        if (is_array($input['tools'] ?? null) && $input['tools'] !== []) {
            return 'coding';
        }

        $text = '';
        foreach ((array) ($input['messages'] ?? []) as $message) {
            if (! is_array($message)) {
                continue;
            }
            $content = $message['content'] ?? '';
            if (is_string($content)) {
                $text .= ' '.$content;

                continue;
            }
            if (is_array($content)) {
                foreach ($content as $part) {
                    if (is_array($part) && ($part['type'] ?? null) === 'image_url') {
                        return 'vision';
                    }
                    if (is_array($part) && is_string($part['text'] ?? null)) {
                        $text .= ' '.$part['text'];
                    }
                }
            }
        }

        $text = mb_strtolower($text);
        if (preg_match('/\b(search|latest|news|web|بحث|ابحث|أحدث|أخبار)\b/u', $text) === 1) {
            return 'search';
        }
        if (preg_match('/\b(code|function|bug|refactor|build|compile|برمجة|كود|أصلح|بناء)\b/u', $text) === 1) {
            return 'coding';
        }
        if (preg_match('/\b(reason|analyze|prove|why|استنتج|حلل|لماذا)\b/u', $text) === 1) {
            return 'reasoning';
        }

        return 'general';
    }

    private function providerHasCredential(AiProvider $provider): bool
    {
        if (trim((string) $provider->api_key) !== '') {
            return true;
        }

        if (in_array($provider->headers, [null, []], true) === false) {
            return true;
        }

        $host = strtolower((string) parse_url((string) $provider->base_url, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    }

    /**
     * Suggest optimal combos based on model analysis.
     * Groups similar models across providers and recommends routing strategies.
     */
    public function suggestCombos(): Collection
    {
        $models = AiModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('organization_id')
                ->where('slug', '!=', self::PROVIDER_SLUG))
            ->get();

        $groups = $models->groupBy(fn (AiModel $model): string => $this->familyKey((string) $model->model_id));
        $suggestions = collect();

        foreach ($groups as $familyKey => $members) {
            if ($members->count() < 2) {
                continue;
            }

            // Analyze capabilities to suggest best strategy
            $hasVision = $members->some(fn ($m) => $m->supports('vision'));
            $hasCoding = $members->some(fn ($m) => $m->supports('tools'));
            $hasSearch = $members->some(fn ($m) => $m->supports('search'));

            $capabilities = [];
            foreach ($members as $member) {
                foreach ((array) ($member->capabilities ?? []) as $capability => $enabled) {
                    if ($enabled === true) {
                        $capabilities[$capability] = true;
                    }
                }
            }

            // Determine best strategy based on capabilities
            $strategy = 'round_robin';
            if ($hasCoding && $hasVision && $hasSearch) {
                $strategy = 'role';
            } elseif ($members->contains(fn ($m) => ! $this->providerHasCredential($m->provider))) {
                $strategy = 'failover';
            }

            $suggestions->push([
                'family_key' => $familyKey,
                'display_name' => ucwords(str_replace(['-', '_'], ' ', $familyKey)),
                'description' => "Auto-group combo for {$members->count()} {$familyKey} variants",
                'member_count' => $members->count(),
                'strategy' => $strategy,
                'capabilities' => $capabilities,
                'members' => $members->map(fn (AiModel $model) => [
                    'id' => $model->getKey(),
                    'name' => $model->display_name ?: $model->model_id,
                    'provider' => $model->provider?->name,
                    'role' => $this->roleFor($model),
                    'priority' => (int) ($model->provider?->priority ?? 0),
                ])->values()->all(),
                'recommended' => true,
            ]);
        }

        return $suggestions->sortByDesc('recommended')->sortByDesc('member_count')->values();
    }

    /**
     * The active, non-combo models that can serve as members, loaded with their
     * provider. A model on the system combo connection is never an eligible
     * member: a combo cannot be built from another combo.
     *
     * @param  list<string>  $memberIds
     * @return Collection<int, AiModel>
     */
    public function eligibleMembers(array $memberIds): Collection
    {
        return AiModel::query()
            ->with('provider')
            ->whereIn('id', $memberIds)
            ->where('is_active', true)
            ->whereHas('provider', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('slug', '!=', self::PROVIDER_SLUG))
            ->get();
    }

    /**
     * The shared hybrid_config contract. Automatic and manual combos store the
     * same member shape so expand() reads one format: an attempt order, a role
     * for task routing, a weight for weighted rotation, and whether the member
     * belongs to the explicit fallback chain.
     *
     * Anything a caller does not send is inherited from $previous, the combo's
     * stored config. Rebuilding the member list must never quietly reset a
     * setting the form did not touch.
     *
     * @param  Collection<int, AiModel>  $members
     * @param  array<string, string>|null  $roles
     * @param  array<string, int|string>|null  $weights
     * @param  list<string>|null  $fallbackChain
     * @param  array<string, int|string>|null  $orders  1-based attempt order per member key
     * @param  array<string, mixed>|null  $previous  the combo's stored hybrid_config
     * @return array<string, mixed>
     */
    private function buildConfig(
        string $strategy,
        Collection $members,
        ?array $roles,
        ?array $weights,
        ?array $fallbackChain,
        bool $auto,
        ?array $orders = null,
        ?array $previous = null,
    ): array {
        $storedMembers = collect($previous['members'] ?? [])
            ->filter(fn (mixed $member): bool => is_array($member) && is_string($member['ai_model_id'] ?? null))
            ->keyBy('ai_model_id');

        $config = [
            'strategy' => $strategy,
            'auto' => $auto,
            'members' => $members->values()->map(function (AiModel $model, int $index) use ($roles, $weights, $fallbackChain, $orders, $storedMembers): array {
                $key = $model->getKey();
                $stored = $storedMembers->get($key);
                $stored = is_array($stored) ? $stored : [];

                $member = [
                    'ai_model_id' => $key,
                    'order' => (int) ($orders[$key] ?? $stored['order'] ?? $index + 1),
                    'role' => $this->roleFor($model),
                    'priority' => (int) ($model->provider?->priority ?? 0),
                ];

                $role = $roles[$key] ?? null;
                $role = is_string($role) && trim($role) !== '' ? $role : ($stored['role'] ?? null);
                if (is_string($role) && trim($role) !== '') {
                    $member['role'] = trim($role);
                }

                // Weight 0 means fallback-only: the member is still tried when
                // everything ahead of it fails, but never chosen first.
                $weight = $weights[$key] ?? null;
                if ($weight !== null && $weight !== '') {
                    $member['weight'] = max(0, min((int) $weight, 100));
                } elseif (array_key_exists('weight', $stored)) {
                    $member['weight'] = max(0, min((int) $stored['weight'], 100));
                }

                // Every member is a fallback unless the operator says otherwise:
                // expand() always keeps the whole list reachable.
                if ($fallbackChain !== null) {
                    $member['in_fallback_chain'] = in_array($key, $fallbackChain, true);
                } else {
                    $member['in_fallback_chain'] = (bool) ($stored['in_fallback_chain'] ?? true);
                }

                return $member;
            })->values()->all(),
        ];

        if (! $auto) {
            $config['manual'] = true;
        }

        return $config;
    }

    /**
     * Create advanced combo with custom configuration.
     *
     * @param  list<string>  $memberIds
     * @param  array<string, string>|null  $roles
     * @param  array<string, int|string>|null  $weights
     * @param  list<string>|null  $fallbackChain
     * @param  array<string, int|string>|null  $orders
     */
    public function createAdvanced(
        string $name,
        ?string $slug,
        string $description,
        string $strategy,
        array $memberIds,
        ?array $roles = null,
        ?array $weights = null,
        ?array $fallbackChain = null,
        ?array $orders = null,
    ): AiModel {
        $provider = $this->systemProvider();
        $members = $this->eligibleMembers($memberIds);

        if ($members->count() !== count($memberIds)) {
            throw new \InvalidArgumentException('Invalid member IDs provided');
        }

        $modelId = trim((string) $slug) ?: Str::slug($name);
        $modelId = mb_substr($modelId, 0, 160);

        $combo = AiModel::query()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => $modelId,
            'display_name' => $name,
            'description' => $description,
            'context_window' => $members->max('context_window'),
            'max_output_tokens' => $members->max('max_output_tokens'),
            'capabilities' => $this->capabilityUnion($members),
            'reasoning_options' => $this->reasoningOptionsUnion($members),
            'hybrid_config' => $this->buildConfig($strategy, $members, $roles, $weights, $fallbackChain, false, $orders),
            'is_active' => true,
            'sort_order' => (int) $members->min('sort_order'),
        ]);

        return $combo;
    }

    /**
     * Reconfigure an existing combo in place. Members, strategy, roles, weights,
     * attempt order and the active flag can all change; hybrid_config is rebuilt
     * from scratch so a removed member is really gone, while settings the caller
     * did not send are carried over from the stored config.
     *
     * @param  list<string>  $memberIds
     * @param  array<string, string>|null  $roles
     * @param  array<string, int|string>|null  $weights
     * @param  list<string>|null  $fallbackChain
     * @param  array<string, int|string>|null  $orders
     */
    public function updateAdvanced(
        AiModel $combo,
        string $name,
        string $description,
        string $strategy,
        array $memberIds,
        ?array $roles = null,
        ?array $weights = null,
        ?array $fallbackChain = null,
        ?array $orders = null,
        bool $isActive = true,
    ): AiModel {
        $members = $this->eligibleMembers($memberIds);

        if ($members->count() !== count($memberIds)) {
            throw new \InvalidArgumentException('Invalid member IDs provided');
        }

        $config = is_array($combo->hybrid_config) ? $combo->hybrid_config : [];
        $auto = (bool) ($config['auto'] ?? false);

        $combo->fill([
            'display_name' => $name,
            'description' => $description,
            'context_window' => $members->max('context_window'),
            'max_output_tokens' => $members->max('max_output_tokens'),
            'capabilities' => $this->capabilityUnion($members),
            'reasoning_options' => $this->reasoningOptionsUnion($members),
            'hybrid_config' => $this->buildConfig($strategy, $members, $roles, $weights, $fallbackChain, $auto, $orders, $config),
            'is_active' => $isActive,
            'sort_order' => (int) $members->min('sort_order'),
        ])->save();

        return $combo;
    }
}
