<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentPermissionPolicy as AgentPermissionPolicyModel;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Support\AgentPermission;
use App\Support\AgentPermissionPolicy;
use App\Support\DesktopSync;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What agents may do on an organization's computers.
 *
 * The desktop already enforces these rules; this is the screen that produces
 * them. Every permission allows until an operator says otherwise, so a page
 * nobody visits behaves exactly like the product before it existed, and the
 * card at the bottom spells out the ordered rules the desktop will receive —
 * the same list the API emits, not a description of it.
 */
class AdminAgentPermissionController extends Controller
{
    public function edit(Request $request): View
    {
        $organizations = Organization::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $organization = $this->selected($request, $organizations);
        $settings = AgentPermissionPolicyModel::settingsFor($organization?->getKey());

        return view('admin.agent-permissions', [
            'organizations' => $organizations,
            'organization' => $organization,
            'sections' => $this->sections($settings),
            'rules' => AgentPermissionPolicy::rules($settings),
            'restricted' => $settings !== [],
            'policy' => $organization instanceof Organization
                ? AgentPermissionPolicyModel::query()->where('organization_id', $organization->getKey())->first()
                : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organizations = Organization::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $organization = $this->selected($request, $organizations);

        if (! $organization instanceof Organization) {
            return redirect()->route('admin.agent-permissions')
                ->withErrors(['organization' => __('ui.admin.agent_permissions.needs_organization')]);
        }

        $validated = $request->validate([
            'organization' => ['required', 'string'],
            'decision' => ['array'],
            'allow' => ['array'],
            'block' => ['array'],
        ] + $this->fieldRules());

        $errors = [];
        $settings = [];

        $decisions = is_array($validated['decision'] ?? null) ? $validated['decision'] : [];
        $allowed = is_array($validated['allow'] ?? null) ? $validated['allow'] : [];
        $blocked = is_array($validated['block'] ?? null) ? $validated['block'] : [];

        foreach (AgentPermission::cases() as $permission) {
            $decision = $decisions[$permission->value] ?? null;
            $allow = $this->lines($allowed[$permission->value] ?? null);
            $block = $this->lines($blocked[$permission->value] ?? null);

            foreach (['allow' => $allow, 'block' => $block] as $list => $patterns) {
                foreach ($patterns as $pattern) {
                    if (AgentPermissionPolicy::normalizePattern($permission, $pattern) === null) {
                        $errors[$list.'.'.$permission->value] = __('ui.admin.agent_permissions.invalid_pattern', [
                            'pattern' => $pattern,
                            'field' => __($permission->labelKey()),
                        ]);
                        break;
                    }
                }
            }

            $settings[$permission->value] = [
                'decision' => is_string($decision) ? $decision : null,
                'allow' => $allow,
                'block' => $block,
            ];
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $before = AgentPermissionPolicyModel::settingsFor($organization->getKey());
        $normalized = AgentPermissionPolicy::normalize($settings);

        $policy = AgentPermissionPolicyModel::query()->firstOrNew(['organization_id' => $organization->getKey()]);
        $policy->settings = $normalized;
        $policy->updated_by = $request->user()?->getKey();
        $policy->save();

        $changes = AgentPermissionPolicy::changes($before, $normalized);

        DesktopSync::announce('agent_permissions_updated', ['organizationId' => $organization->getKey()]);

        if ($changes === []) {
            return redirect()->route('admin.agent-permissions', ['organization' => $organization->getKey()])
                ->with('status', __('ui.common.saved'));
        }

        AuditLog::record(
            'admin.agent_permissions.updated',
            $organization,
            $policy,
            AgentPermissionPolicy::describe($changes) ?? __('ui.admin.agent_permissions.title'),
            ['agent_permissions' => $changes],
        );

        return redirect()->route('admin.agent-permissions', ['organization' => $organization->getKey()])
            ->with('status', __('ui.common.saved'));
    }

    /**
     * The catalogue as the form shows it: one card per section, one row per
     * permission, with the setting it currently holds.
     *
     * @param  array<string, array{decision?: string, allow?: list<string>, block?: list<string>}>  $settings
     * @return list<array{key: string, label: string, rows: list<array<string, mixed>>}>
     */
    private function sections(array $settings): array
    {
        $sections = [];

        foreach (AgentPermission::cases() as $permission) {
            $setting = $settings[$permission->value] ?? null;
            $section = $permission->section();

            $sections[$section] ??= [
                'key' => $section,
                'label' => __('ui.admin.agent_permissions.sections.'.$section),
                'rows' => [],
            ];

            $sections[$section]['rows'][] = [
                'permission' => $permission,
                'name' => __($permission->labelKey()),
                'body' => __($permission->bodyKey()),
                'decision' => $setting['decision'] ?? 'allow',
                'allow' => implode("\n", $setting['allow'] ?? []),
                'block' => implode("\n", $setting['block'] ?? []),
                'patterns' => $permission->patternKind(),
                'decisions' => array_map(
                    fn (string $decision): array => [
                        'value' => $decision,
                        'label' => __('ui.admin.agent_permissions.decision_'.$decision),
                    ],
                    $permission->decisions(),
                ),
            ];
        }

        return array_values($sections);
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

    /**
     * @return array<string, mixed>
     */
    private function fieldRules(): array
    {
        $rules = [];

        foreach (AgentPermission::cases() as $permission) {
            // Every decision is accepted here and coerced in the policy: a
            // switch sent "ask" is stored as block, never as a prompt the
            // screen cannot show.
            $rules['decision.'.$permission->value] = ['nullable', Rule::in(AgentPermissionPolicy::DECISIONS)];
            $rules['allow.'.$permission->value] = ['nullable', 'string', 'max:4000'];
            $rules['block.'.$permission->value] = ['nullable', 'string', 'max:4000'];
        }

        return $rules;
    }

    /**
     * A textarea of one pattern per line, without blanks.
     *
     * @return list<string>
     */
    private function lines(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $patterns = [];

        foreach ($lines as $line) {
            $pattern = trim($line);

            if ($pattern !== '') {
                $patterns[] = $pattern;
            }
        }

        return $patterns;
    }
}
