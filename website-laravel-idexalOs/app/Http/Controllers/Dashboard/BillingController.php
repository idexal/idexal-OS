<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Services\BillingService;
use App\Services\OrganizationContext;
use App\Services\PaymentGatewayService;
use App\Services\SettingsService;
use App\Services\StripeService;
use App\Services\UsageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly BillingService $billing,
        private readonly StripeService $stripe,
        private readonly SettingsService $settings,
        private readonly UsageService $usage,
        private readonly PaymentGatewayService $gateways,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $subscription = $organization->currentSubscription();

        // A user who arrived from the desktop's sign-in gate and has now picked
        // a plan goes straight back to connecting the desktop, rather than being
        // left on the billing page inside a sign-in window with no way onward.
        // This also covers a return from a hosted checkout, where the plan is
        // active by the time the billing page loads. The flag is consumed so a
        // later, ordinary visit to billing is not redirected.
        if ($subscription !== null && $request->session()->pull('desktop_auth', false)) {
            return redirect()->route('desktop.authorize');
        }

        $plan = $organization->currentPlan();

        return view('dashboard.billing.index', [
            'organization' => $organization,
            'plan' => $plan,
            'subscription' => $subscription,
            'plans' => Plan::query()
                ->where('is_public', true)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'invoices' => $organization->invoices()->latest('issued_at')->latest('created_at')->limit(8)->get(),
            'paymentMethods' => $organization->paymentMethods()->orderByDesc('is_default')->latest('created_at')->get(),
            'creditTransactions' => $organization->creditTransactions()->latest('created_at')->limit(10)->get(),
            'summary' => $this->usage->summary($organization),
            'stripeEnabled' => $this->stripe->enabled(),
            'availableGateways' => $this->gateways->availableGateways(),
            'canManage' => $this->context->canManageBilling(),
            'billingEnabled' => $this->settings->boolean('billing_enabled', true),
        ]);
    }

    public function changePlan(Request $request): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeBilling();

        $validated = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        $plan = Plan::query()
            ->where('slug', $validated['plan'])
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $result = $this->billing->changePlan($organization, $plan, $validated['billing_cycle'], $request->user());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['plan' => __('ui.common.something_wrong')]);
        }

        AuditLog::record('billing.plan.changed', $organization, $plan, $plan->name);

        if ($result['type'] === 'stripe' && isset($result['url'])) {
            return redirect()->away($result['url']);
        }

        if ($result['type'] === 'invoice' && isset($result['invoice'])) {
            return redirect()->route('dashboard.invoices.show', $result['invoice'])
                ->with('status', __('ui.billing.manual_invoice_created'));
        }

        return redirect()->route('dashboard.billing')->with('status', __('ui.common.saved'));
    }

    public function cancel(): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeBilling();

        $this->billing->cancel($organization);
        AuditLog::record('billing.subscription.canceled', $organization);

        return redirect()->route('dashboard.billing')->with('status', __('ui.billing.canceled'));
    }

    public function topUp(Request $request): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeBilling();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:5', 'max:10000'],
        ]);

        try {
            $result = $this->billing->topUpCredits($organization, (float) $validated['amount'], $request->user());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['amount' => __('ui.common.something_wrong')]);
        }

        AuditLog::record('billing.credits.top_up', $organization, null, (string) $validated['amount']);

        if ($result['type'] === 'stripe' && isset($result['url'])) {
            return redirect()->away($result['url']);
        }

        if ($result['type'] === 'invoice' && isset($result['invoice'])) {
            return redirect()->route('dashboard.invoices.show', $result['invoice'])
                ->with('status', __('ui.billing.manual_invoice_created'));
        }

        return redirect()->route('dashboard.billing')->with('status', __('ui.common.saved'));
    }

    public function portal(): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeBilling();

        if (! $this->stripe->enabled()) {
            return redirect()->route('dashboard.billing')->withErrors(['stripe' => __('ui.errors.stripe_price_missing')]);
        }

        try {
            $session = $this->stripe->createPortalSession($organization);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('dashboard.billing')->withErrors(['stripe' => __('ui.common.something_wrong')]);
        }

        return redirect()->away((string) $session->url);
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeBilling();

        $validated = $request->validate([
            'type' => ['required', Rule::in([PaymentMethod::TYPE_BANK_TRANSFER, PaymentMethod::TYPE_CARD, PaymentMethod::TYPE_CASH])],
            'holder_name' => ['required', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:40'],
            'last4' => ['nullable', 'string', 'size:4'],
            'exp_month' => ['nullable', 'integer', 'between:1,12', 'required_with:exp_year'],
            'exp_year' => ['nullable', 'integer', 'between:'.now()->year.',2100', 'required_with:exp_month'],
            'iban' => ['nullable', 'string', 'max:64'],
        ]);

        $isDefault = ! $organization->paymentMethods()->exists();

        if ($isDefault) {
            $organization->paymentMethods()->update(['is_default' => false]);
        }

        $method = $organization->paymentMethods()->create([
            'type' => $validated['type'],
            'brand' => $validated['brand'] ?? null,
            'last4' => $validated['last4'] ?? null,
            'exp_month' => $validated['exp_month'] ?? null,
            'exp_year' => $validated['exp_year'] ?? null,
            'holder_name' => $validated['holder_name'],
            'gateway' => 'manual',
            'is_default' => $isDefault,
            'metadata' => array_filter(['iban' => $validated['iban'] ?? null]),
        ]);

        AuditLog::record('billing.payment_method.created', $organization, $method, $method->label());

        return redirect()->route('dashboard.billing')->with('status', __('ui.common.saved'));
    }

    public function makeDefault(PaymentMethod $paymentMethod): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeBilling();
        abort_unless($paymentMethod->organization_id === $organization->getKey(), 404);

        $organization->paymentMethods()->whereKeyNot($paymentMethod->getKey())->update(['is_default' => false]);
        $paymentMethod->forceFill(['is_default' => true])->save();

        return redirect()->route('dashboard.billing')->with('status', __('ui.common.saved'));
    }

    public function destroyPaymentMethod(PaymentMethod $paymentMethod): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        $this->authorizeBilling();
        abort_unless($paymentMethod->organization_id === $organization->getKey(), 404);

        $paymentMethod->delete();
        AuditLog::record('billing.payment_method.deleted', $organization);

        return redirect()->route('dashboard.billing')->with('status', __('ui.common.deleted'));
    }

    private function authorizeBilling(): void
    {
        if (! $this->context->canManageBilling()) {
            abort(403, __('ui.errors.not_allowed'));
        }
    }
}
