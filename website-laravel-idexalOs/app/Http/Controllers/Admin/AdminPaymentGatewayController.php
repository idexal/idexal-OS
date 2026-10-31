<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\PaymentGatewayService;
use App\Services\SettingsService;
use App\Support\DesktopSync;
use App\Support\SettingsChanges;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * One page that switches payment gateways on and off and holds their settings.
 * Secrets are write-only: a stored value is never sent back to the browser, and
 * leaving a secret field blank keeps the stored one.
 */
class AdminPaymentGatewayController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly PaymentGatewayService $gateways,
    ) {}

    public function edit(): View
    {
        return view('admin.payment-gateways', [
            'values' => [
                'stripe_enabled' => $this->settings->boolean('stripe_enabled', true),
                'stripe_key' => $this->settings->get('stripe_key'),
                'paypal_enabled' => $this->settings->boolean('paypal_enabled'),
                'paypal_mode' => $this->settings->get('paypal_mode', 'sandbox'),
                'paypal_client_id' => $this->settings->get('paypal_client_id'),
                'paypal_webhook_id' => $this->settings->get('paypal_webhook_id'),
                'bank_transfer_enabled' => $this->settings->boolean('bank_transfer_enabled'),
                'bank_transfer_bank_name' => $this->settings->get('bank_transfer_bank_name'),
                'bank_transfer_account_name' => $this->settings->get('bank_transfer_account_name'),
                'bank_transfer_account_number' => $this->settings->get('bank_transfer_account_number'),
                'bank_transfer_iban' => $this->settings->get('bank_transfer_iban'),
                'bank_transfer_swift' => $this->settings->get('bank_transfer_swift'),
                'bank_transfer_routing_number' => $this->settings->get('bank_transfer_routing_number'),
                'bank_transfer_instructions' => $this->settings->get('bank_transfer_instructions'),
            ],
            'hasStripeSecret' => filled($this->settings->get('stripe_secret')),
            'hasStripeWebhookSecret' => filled($this->settings->get('stripe_webhook_secret')),
            'hasPayPalSecret' => filled($this->settings->get('paypal_secret')),
            'activeGateways' => $this->gateways->availableGateways(),
            'stripeWebhookUrl' => url('/webhooks/stripe'),
            'paypalWebhookUrl' => url('/webhooks/paypal'),
            'bankTransferReady' => $this->gateways->bankTransferReady(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stripe_enabled' => ['nullable', 'boolean'],
            'stripe_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],

            'paypal_enabled' => ['nullable', 'boolean'],
            'paypal_mode' => ['nullable', Rule::in(['sandbox', 'live'])],
            'paypal_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_secret' => ['nullable', 'string', 'max:255'],
            'paypal_webhook_id' => ['nullable', 'string', 'max:255'],

            'bank_transfer_enabled' => ['nullable', 'boolean'],
            'bank_transfer_bank_name' => ['nullable', 'string', 'max:190'],
            'bank_transfer_account_name' => ['nullable', 'string', 'max:190'],
            'bank_transfer_account_number' => ['nullable', 'string', 'max:64'],
            'bank_transfer_iban' => ['nullable', 'string', 'max:64'],
            'bank_transfer_swift' => ['nullable', 'string', 'max:32'],
            'bank_transfer_routing_number' => ['nullable', 'string', 'max:32'],
            'bank_transfer_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $values = [
            'stripe_enabled' => $request->boolean('stripe_enabled'),
            'stripe_key' => $validated['stripe_key'] ?? '',

            'paypal_enabled' => $request->boolean('paypal_enabled'),
            'paypal_mode' => $validated['paypal_mode'] ?? 'sandbox',
            'paypal_client_id' => $validated['paypal_client_id'] ?? '',
            'paypal_webhook_id' => $validated['paypal_webhook_id'] ?? '',

            'bank_transfer_enabled' => $request->boolean('bank_transfer_enabled'),
            'bank_transfer_bank_name' => $validated['bank_transfer_bank_name'] ?? '',
            'bank_transfer_account_name' => $validated['bank_transfer_account_name'] ?? '',
            'bank_transfer_account_number' => $validated['bank_transfer_account_number'] ?? '',
            'bank_transfer_iban' => $validated['bank_transfer_iban'] ?? '',
            'bank_transfer_swift' => $validated['bank_transfer_swift'] ?? '',
            'bank_transfer_routing_number' => $validated['bank_transfer_routing_number'] ?? '',
            'bank_transfer_instructions' => $validated['bank_transfer_instructions'] ?? '',
        ];

        // Secrets are write-only. A blank field keeps whatever is stored; the
        // explicit clear flags remove a value.
        foreach (['stripe_secret', 'stripe_webhook_secret'] as $secret) {
            if (filled($validated[$secret] ?? null)) {
                $values[$secret] = $validated[$secret];
            }

            if ($request->boolean('clear_'.$secret)) {
                $values[$secret] = null;
            }
        }

        if (filled($validated['paypal_secret'] ?? null)) {
            $values['paypal_secret'] = $validated['paypal_secret'];
        }

        if ($request->boolean('clear_paypal_secret')) {
            $values['paypal_secret'] = null;
        }

        // A gateway cannot be switched on without the details it needs to work,
        // otherwise a customer would pick it and hit a dead end.
        $errors = $this->readinessErrors($values);

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $before = SettingsChanges::snapshot(array_keys($values));

        $this->settings->setMany($values, 'billing');

        $changes = SettingsChanges::between($before, $values, ['stripe_secret', 'stripe_webhook_secret', 'paypal_secret']);

        if ($changes === []) {
            return redirect()->route('admin.payment-gateways')->with('status', __('ui.common.saved'));
        }

        AuditLog::record(
            'admin.payment_gateways.updated',
            null,
            null,
            implode(', ', $this->gateways->availableGateways()),
            ['settings' => $changes],
        );
        DesktopSync::announce('payment_gateways_updated');

        return redirect()->route('admin.payment-gateways')->with('status', __('ui.common.saved'));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function readinessErrors(array $values): array
    {
        $errors = [];
        $merged = fn (string $key): mixed => $values[$key] ?? $this->settings->get($key);

        if (($values['stripe_enabled'] ?? false)
            && (trim((string) $merged('stripe_key')) === '' || $this->missingSecret('stripe_secret', $values))) {
            $errors['stripe_key'] = __('ui.admin.payment_gateways.stripe_incomplete');
        }

        if (($values['paypal_enabled'] ?? false)
            && (trim((string) $merged('paypal_client_id')) === '' || $this->missingSecret('paypal_secret', $values))) {
            $errors['paypal_client_id'] = __('ui.admin.payment_gateways.paypal_incomplete');
        }

        if ($values['bank_transfer_enabled'] ?? false) {
            $hasAccount = trim((string) $merged('bank_transfer_iban')) !== ''
                || trim((string) $merged('bank_transfer_account_number')) !== '';

            if (! $hasAccount) {
                $errors['bank_transfer_iban'] = __('ui.admin.payment_gateways.bank_incomplete');
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function missingSecret(string $key, array $values): bool
    {
        if (array_key_exists($key, $values) && filled($values[$key])) {
            return false;
        }

        return ! filled($this->settings->get($key));
    }
}
