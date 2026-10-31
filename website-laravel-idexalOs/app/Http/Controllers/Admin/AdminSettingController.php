<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\SettingsService;
use App\Support\DesktopSync;
use App\Support\SettingsChanges;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings', [
            'values' => [
                'site_name' => $this->settings->get('site_name'),
                'site_tagline' => $this->settings->get('site_tagline'),
                'support_email' => $this->settings->get('support_email'),
                'registration_enabled' => $this->settings->boolean('registration_enabled', true),
                'require_email_verification' => $this->settings->boolean('require_email_verification', false),
                'gateway_enabled' => $this->settings->boolean('gateway_enabled', true),
                'proxy_enabled' => $this->settings->boolean('proxy_enabled', true),
                'browser_prewarm_enabled' => $this->settings->boolean('browser_prewarm_enabled', true),
                'billing_enabled' => $this->settings->boolean('billing_enabled', true),
                'default_currency' => $this->settings->get('default_currency', 'USD'),
                'tax_rate' => $this->settings->get('tax_rate', 0),
                'invoice_due_days' => $this->settings->integer('invoice_due_days', 14),
                'payment_instructions' => $this->settings->get('payment_instructions', ''),
                'desktop_latest_version' => $this->settings->get('desktop_latest_version', '0.0.0'),
                'desktop_min_version' => $this->settings->get('desktop_min_version', '0.0.0'),
                'maintenance_message' => $this->settings->get('maintenance_message', ''),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:190'],
            'support_email' => ['required', 'email', 'max:190'],
            'default_currency' => ['required', 'string', 'size:3'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_due_days' => ['required', 'integer', 'min:1', 'max:365'],
            'payment_instructions' => ['nullable', 'string', 'max:5000'],
            'desktop_latest_version' => ['required', 'string', 'max:40'],
            'desktop_min_version' => ['required', 'string', 'max:40'],
            'maintenance_message' => ['nullable', 'string', 'max:2000'],
            'stripe_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $values = [
            'site_name' => $validated['site_name'],
            'site_tagline' => $validated['site_tagline'] ?? '',
            'support_email' => $validated['support_email'],
            'registration_enabled' => $request->boolean('registration_enabled'),
            'require_email_verification' => $request->boolean('require_email_verification'),
            'gateway_enabled' => $request->boolean('gateway_enabled'),
            'proxy_enabled' => $request->boolean('proxy_enabled'),
            'browser_prewarm_enabled' => $request->boolean('browser_prewarm_enabled'),
            'billing_enabled' => $request->boolean('billing_enabled'),
            'default_currency' => strtoupper($validated['default_currency']),
            'tax_rate' => $validated['tax_rate'],
            'invoice_due_days' => $validated['invoice_due_days'],
            'payment_instructions' => $validated['payment_instructions'] ?? '',
            'desktop_latest_version' => $validated['desktop_latest_version'],
            'desktop_min_version' => $validated['desktop_min_version'],
            'maintenance_message' => $validated['maintenance_message'] ?? '',
        ];

        // Stripe now lives on the dedicated payment gateways page. Keep this
        // endpoint accepting the keys so existing clients keep working, but
        // never blank a stored secret from a form that no longer posts it.
        if ($request->has('stripe_key')) {
            $values['stripe_key'] = $validated['stripe_key'] ?? '';
        }

        if (filled($validated['stripe_secret'] ?? null)) {
            $values['stripe_secret'] = $validated['stripe_secret'];
        }

        if (filled($validated['stripe_webhook_secret'] ?? null)) {
            $values['stripe_webhook_secret'] = $validated['stripe_webhook_secret'];
        }

        if ($request->boolean('clear_stripe_secret')) {
            $values['stripe_secret'] = null;
        }

        if ($request->boolean('clear_stripe_webhook_secret')) {
            $values['stripe_webhook_secret'] = null;
        }

        // The snapshot has to be read after every value (including the write-only
        // secrets) is in $values, but before setMany replaces what is stored.
        $before = SettingsChanges::snapshot(array_keys($values));

        $this->settings->setMany($values, 'platform');

        $changes = SettingsChanges::between($before, $values, ['stripe_secret', 'stripe_webhook_secret']);

        // Saving the page without editing it is not a configuration change.
        if ($changes === []) {
            return redirect()->route('admin.settings')->with('status', __('ui.common.saved'));
        }

        AuditLog::record('admin.settings.updated', null, null, __('ui.admin.settings.title'), ['settings' => $changes]);
        DesktopSync::announce('settings_updated');

        return redirect()->route('admin.settings')->with('status', __('ui.common.saved'));
    }
}
