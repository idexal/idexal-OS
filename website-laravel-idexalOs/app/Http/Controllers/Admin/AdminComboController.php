<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AuditLog;
use App\Services\ComboModelService;
use App\Support\DesktopSync;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminComboController extends Controller
{
    /** Routing strategies a combo can use. */
    private const STRATEGIES = [
        'round_robin' => 'Round robin (rotate equally)',
        'weighted' => 'Weighted (share traffic by weight)',
        'role' => 'Task routing (best model per task)',
        'failover' => 'Failover (primary, then backup)',
    ];

    public function __construct(private ComboModelService $comboService) {}

    /**
     * Show combos and the member models a new combo can be built from.
     *
     * A combo is any ai_models row carrying a hybrid_config; the models a
     * combo can be assembled from are the active, non-combo rows on real
     * provider connections.
     */
    public function index(): View
    {
        $combos = AiModel::query()
            ->whereNotNull('hybrid_config')
            ->with('provider')
            ->orderByDesc('is_active')
            ->orderBy('display_name')
            ->paginate(50);

        $members = AiModel::query()
            ->where('is_active', true)
            ->whereNull('hybrid_config')
            ->whereHas('provider', fn ($query) => $query->where('slug', '!=', ComboModelService::PROVIDER_SLUG))
            ->with('provider')
            ->orderBy('display_name')
            ->get();

        $eligibleMemberIds = $members->pluck('id')->all();

        // A stored member that is no longer eligible — deactivated, deleted, or
        // turned into a combo — cannot be offered as a checkbox, yet the combo
        // still counts it. Load it so the editor can name it instead of being
        // silently short a member.
        $unavailableMemberIds = $combos->getCollection()
            ->flatMap(fn (AiModel $combo): array => collect($combo->hybrid_config['members'] ?? [])
                ->pluck('ai_model_id')
                ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                ->all())
            ->unique()
            ->reject(fn (string $id): bool => in_array($id, $eligibleMemberIds, true))
            ->values();

        $unavailableMembers = AiModel::query()
            ->with('provider')
            ->whereIn('id', $unavailableMemberIds->all())
            ->get()
            ->keyBy('id');

        return view('admin.combos.index', [
            'combos' => $combos,
            'strategies' => self::STRATEGIES,
            'members' => $members,
            'eligibleMemberIds' => $eligibleMemberIds,
            'unavailableMembers' => $unavailableMembers,
        ]);
    }

    /**
     * Rebuild automatic combos from models that share an id across providers.
     */
    public function sync(): RedirectResponse
    {
        $count = $this->comboService->syncAutomaticCombos();
        DesktopSync::announce('combo_rebuilt', ['count' => $count]);

        return redirect()->route('admin.combos.index')
            ->with('status', __('ui.admin.combos.rebuilt', ['count' => $count]));
    }

    /**
     * Store a newly created hybrid model using the shared combo contract:
     * a public slug desktops subscribe to, a routing strategy, and the
     * non-hybrid members that rotate underneath it.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'slug' => 'nullable|string|max:160|regex:/^[a-z0-9-]+$/',
            'description' => 'nullable|string|max:1000',
            'strategy' => 'required|in:round_robin,weighted,role,failover',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'string',
            'roles' => 'nullable|array',
            'weights' => 'nullable|array',
            'fallback_chain' => 'nullable|array',
            'orders' => 'nullable|array',
            'orders.*' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]);

        $slug = filled($validated['slug'] ?? null) ? $validated['slug'] : Str::slug($validated['name']);

        // A combo may not shadow the public id of an existing model or combo.
        if (AiModel::query()->where('model_id', $slug)->exists()) {
            return back()->withInput()->withErrors([
                'slug' => __('ui.admin.combos.slug_taken', ['slug' => $slug]),
            ]);
        }

        // A combo may not be built from other combos.
        $comboMemberCount = AiModel::query()
            ->whereIn('id', $validated['member_ids'])
            ->whereNotNull('hybrid_config')
            ->count();

        if ($comboMemberCount > 0) {
            return back()->withInput()->withErrors([
                'member_ids' => __('ui.admin.combos.nested_member'),
            ]);
        }

        try {
            $combo = $this->comboService->createAdvanced(
                $validated['name'],
                $slug,
                (string) ($validated['description'] ?? ''),
                $validated['strategy'],
                array_values($validated['member_ids']),
                $validated['roles'] ?? null,
                $validated['weights'] ?? null,
                $validated['fallback_chain'] ?? null,
                $validated['orders'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors([
                'member_ids' => __('ui.admin.combos.members_invalid'),
            ]);
        }

        if (! ($validated['is_active'] ?? true)) {
            $combo->forceFill(['is_active' => false])->save();
        }

        AuditLog::record('admin.combo.created', null, $combo, __('ui.admin.combos.created', ['name' => $combo->display_name]));
        DesktopSync::announce('combo_created', ['model_id' => $combo->model_id]);

        return redirect()->route('admin.combos.index')
            ->with('status', __('ui.admin.combos.created_delivered', ['name' => $combo->display_name]));
    }

    /**
     * The advanced-creation form posts the same payload as store().
     */
    public function storeAdvanced(Request $request): RedirectResponse
    {
        return $this->store($request);
    }

    /**
     * Reconfigure an existing combo without deleting it, so its public slug and
     * every desktop subscription to it survive the change.
     */
    public function update(Request $request, string $combo): RedirectResponse
    {
        $model = AiModel::findOrFail($combo);

        if (! filled($model->hybrid_config)) {
            return back()->withErrors(['error' => __('ui.admin.combos.edit_only')]);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:190',
                'description' => 'nullable|string|max:1000',
                'strategy' => 'required|in:round_robin,weighted,role,failover',
                'member_ids' => 'required|array|min:1',
                'member_ids.*' => 'string',
                'roles' => 'nullable|array',
                'weights' => 'nullable|array',
                'fallback_chain' => 'nullable|array',
                'orders' => 'nullable|array',
                'orders.*' => 'nullable|integer|min:0|max:999',
                'is_active' => 'nullable|boolean',
            ]);
        } catch (ValidationException $exception) {
            return $this->editFailed($model, $exception->errors());
        }

        $comboMemberCount = AiModel::query()
            ->whereIn('id', $validated['member_ids'])
            ->whereNotNull('hybrid_config')
            ->whereKeyNot($model->getKey())
            ->count();

        if ($comboMemberCount > 0) {
            return $this->editFailed($model, [
                'member_ids' => __('ui.admin.combos.nested_member'),
            ]);
        }

        try {
            $this->comboService->updateAdvanced(
                $model,
                $validated['name'],
                (string) ($validated['description'] ?? ''),
                $validated['strategy'],
                array_values($validated['member_ids']),
                $validated['roles'] ?? null,
                $validated['weights'] ?? null,
                $validated['fallback_chain'] ?? null,
                $validated['orders'] ?? null,
                (bool) ($validated['is_active'] ?? false),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->editFailed($model, [
                'member_ids' => __('ui.admin.combos.members_invalid'),
            ]);
        }

        AuditLog::record('admin.combo.updated', null, $model, __('ui.admin.combos.updated', ['name' => $model->display_name]));
        DesktopSync::announce('combo_updated', ['model_id' => $model->model_id]);

        return redirect()->route('admin.combos.index')
            ->with('status', __('ui.admin.combos.updated', ['name' => $model->display_name]));
    }

    /**
     * Return to the combo the operator was editing with that panel reopened and
     * the typed values intact, so a rejected save never looks like a lost one.
     *
     * @param  array<string, mixed>  $errors
     */
    private function editFailed(AiModel $combo, array $errors): RedirectResponse
    {
        return back()
            ->withInput()
            ->with('combo_edit', $combo->getKey())
            ->withErrors($errors);
    }

    /**
     * Suggest combos automatically detected across providers.
     */
    public function suggest(): View
    {
        return view('admin.combos.suggest', [
            'suggestions' => $this->comboService->suggestCombos(),
        ]);
    }

    /**
     * Delete a combo. Its member models return to the desktop catalog
     * automatically: they are only hidden while the combo that claims them
     * exists.
     */
    public function destroy(string $combo): RedirectResponse
    {
        $model = AiModel::findOrFail($combo);

        if (filled($model->hybrid_config)) {
            $model->delete();
            AuditLog::record('admin.combo.deleted', null, null, __('ui.admin.combos.deleted', ['name' => $model->display_name]));
            DesktopSync::announce('combo_deleted', ['model_id' => $model->model_id]);

            return redirect()->route('admin.combos.index')
                ->with('status', __('ui.admin.combos.deleted_released', ['name' => $model->display_name]));
        }

        return redirect()->back()->withErrors(['error' => __('ui.admin.combos.delete_only')]);
    }
}
