<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Throwable;

class BillingService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly InvoiceService $invoices,
        private readonly StripeService $stripe,
    ) {}

    /**
     * @return array{type: string, subscription: Subscription, url?: string, invoice?: Invoice}
     */
    public function changePlan(
        Organization $organization,
        Plan $plan,
        string $billingCycle,
        User $user,
    ): array {
        if ($plan->isFree()) {
            $subscription = $this->subscriptions->start($organization, $plan, 'monthly');

            return [
                'type' => 'subscription',
                'subscription' => $subscription,
            ];
        }

        if ($this->stripe->isConfiguredForPlan($plan, $billingCycle)) {
            $subscription = $this->subscriptions->pending($organization, $plan, $billingCycle);

            try {
                $session = $this->stripe->createPlanCheckoutSession(
                    $organization,
                    $plan,
                    $billingCycle,
                    $user,
                    $subscription,
                );
            } catch (Throwable $exception) {
                $subscription->delete();

                throw $exception;
            }

            return [
                'type' => 'stripe',
                'subscription' => $subscription,
                'url' => (string) $session->url,
            ];
        }

        $subscription = $this->subscriptions->pending($organization, $plan, $billingCycle);
        $invoice = $this->invoices->createPlanInvoice($organization, $plan, $billingCycle, $subscription);

        return [
            'type' => 'invoice',
            'subscription' => $subscription,
            'invoice' => $invoice,
        ];
    }

    /**
     * @return array{type: string, url?: string, invoice?: Invoice}
     */
    public function topUpCredits(Organization $organization, float $amount, User $user): array
    {
        if ($this->stripe->enabled()) {
            $session = $this->stripe->createCreditCheckoutSession($organization, $amount, $user);

            return [
                'type' => 'stripe',
                'url' => (string) $session->url,
            ];
        }

        $invoice = $this->invoices->createCreditTopUpInvoice($organization, $amount, $user);

        return [
            'type' => 'invoice',
            'invoice' => $invoice,
        ];
    }

    public function cancel(Organization $organization): void
    {
        $this->subscriptions->cancel($organization);
    }
}
