<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\OrganizationContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function edit(): View
    {
        $organization = $this->context->requireOrganization();

        return view('dashboard.settings', [
            'organization' => $organization,
            'canManage' => $this->context->canManageMembers(),
            'canManageBilling' => $this->context->canManageBilling(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->context->requireOrganization();

        if (! $this->context->canManageMembers()) {
            abort(403, __('ui.errors.not_allowed'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'billing_email' => ['nullable', 'email', 'max:190'],
            'currency' => ['required', 'string', 'size:3'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'address_line_1' => ['nullable', 'string', 'max:190'],
            'address_line_2' => ['nullable', 'string', 'max:190'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'tax_id' => ['nullable', 'string', 'max:60'],
        ]);

        $organization->forceFill([
            ...$validated,
            'currency' => strtoupper($validated['currency']),
            'country' => $validated['country'] !== null ? strtoupper($validated['country']) : null,
        ])->save();

        AuditLog::record('organization.updated', $organization, $organization, $organization->name);

        return redirect()->route('dashboard.settings')->with('status', __('ui.common.saved'));
    }
}
