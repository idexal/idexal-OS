<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return $this->settings->stripeEnabled();
    }

    public function client(): StripeClient
    {
        $credentials = $this->settings->stripeCredentials();

        if ($credentials['secret'] === null) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        return new StripeClient($credentials['secret']);
    }

    public function ensureCustomer(Organization $organization): string
    {
        if (is_string($organization->stripe_customer_id) && $organization->stripe_customer_id !== '') {
            return $organization->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'name' => $organization->name,
            'email' => $organization->billing_email ?: $organization->owner?->email,
            'metadata' => [
                'organization_id' => $organization->getKey(),
                'organization_slug' => $organization->slug,
            ],
        ]);

        $organization->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    public function createPlanCheckoutSession(
        Organization $organization,
        Plan $plan,
        string $billingCycle,
        User $user,
        Subscription $subscription,
    ): Session {
        $priceId = $plan->stripePriceId($billingCycle);

        if (! is_string($priceId) || $priceId === '') {
            throw new \RuntimeException(__('ui.errors.stripe_price_missing'));
        }

        $metadata = [
            'organization_id' => $organization->getKey(),
            'plan_id' => $plan->getKey(),
            'subscription_id' => $subscription->getKey(),
            'billing_cycle' => $billingCycle,
            'user_id' => $user->getKey(),
        ];

        return $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $this->ensureCustomer($organization),
            'client_reference_id' => $organization->getKey(),
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => route('dashboard.billing', ['checkout' => 'success']),
            'cancel_url' => route('dashboard.billing', ['checkout' => 'cancel']),
            'allow_promotion_codes' => true,
            'metadata' => $metadata,
            'subscription_data' => [
                'metadata' => $metadata,
            ],
        ]);
    }

    public function createCreditCheckoutSession(Organization $organization, float $amount, User $user): Session
    {
        $currency = strtolower((string) ($organization->currency ?: $this->settings->get('default_currency', 'USD')));

        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $this->ensureCustomer($organization),
            'client_reference_id' => $organization->getKey(),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) round($amount * 100),
                    'product_data' => [
                        'name' => __('ui.billing.credit_top_up_line'),
                        'description' => __('ui.billing.credit_top_up_note'),
                    ],
                ],
            ]],
            'success_url' => route('dashboard.billing', ['checkout' => 'credit']),
            'cancel_url' => route('dashboard.billing', ['checkout' => 'cancel']),
            'metadata' => [
                'organization_id' => $organization->getKey(),
                'credit_amount' => (string) $amount,
                'user_id' => $user->getKey(),
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'organization_id' => $organization->getKey(),
                    'credit_amount' => (string) $amount,
                    'user_id' => $user->getKey(),
                ],
            ],
        ]);
    }

    public function createPortalSession(Organization $organization): Session
    {
        return $this->client()->billingPortal->sessions->create([
            'customer' => $this->ensureCustomer($organization),
            'return_url' => route('dashboard.billing'),
        ]);
    }

    public function constructEvent(string $payload, string $signature): Event
    {
        $credentials = $this->settings->stripeCredentials();

        if ($credentials['webhook_secret'] === null) {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, $signature, $credentials['webhook_secret']);
    }

    public function retrieveSubscription(string $id): \Stripe\Subscription
    {
        return $this->client()->subscriptions->retrieve($id);
    }

    public function retrieveInvoice(string $id): Invoice
    {
        return $this->client()->invoices->retrieve($id);
    }

    public function isConfiguredForPlan(Plan $plan, string $billingCycle): bool
    {
        return $this->enabled() && filled($plan->stripePriceId($billingCycle));
    }
}
