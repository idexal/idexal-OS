<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\StripeEvent;
use App\Models\Subscription;
use App\Services\CreditService;
use App\Services\InvoiceService;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Event as StripeWebhookEvent;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly SubscriptionService $subscriptions,
        private readonly InvoiceService $invoices,
        private readonly CreditService $credits,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');

        try {
            $event = $this->stripe->constructEvent($payload, $signature);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['received' => false, 'error' => 'invalid_signature'], 400);
        }

        $record = StripeEvent::query()->firstOrCreate(
            ['id' => $event->id],
            [
                'type' => $event->type,
                'payload' => $event->toArray(),
            ],
        );

        if ($record->processed_at !== null) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        try {
            $this->handle($event);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['received' => false, 'error' => 'processing_failed'], 500);
        }

        $record->forceFill(['processed_at' => now()])->save();

        return response()->json(['received' => true]);
    }

    private function handle(StripeWebhookEvent $event): void
    {
        match ($event->type) {
            'checkout.session.completed' => $this->checkoutCompleted($event),
            'customer.subscription.updated', 'customer.subscription.deleted' => $this->subscriptionChanged($event),
            'invoice.paid' => $this->invoicePaid($event),
            'invoice.payment_failed' => $this->invoiceFailed($event),
            default => null,
        };
    }

    private function checkoutCompleted(StripeWebhookEvent $event): void
    {
        $session = $event->data->object;
        $metadata = $this->metadata($session);
        $organization = Organization::query()->find($metadata['organization_id'] ?? '');

        if (! $organization instanceof Organization) {
            return;
        }

        if (($session->mode ?? null) === 'subscription') {
            $subscription = Subscription::query()->find($metadata['subscription_id'] ?? '');
            $plan = Plan::query()->find($metadata['plan_id'] ?? '');

            if ($subscription instanceof Subscription && $plan instanceof Plan) {
                $updated = $this->subscriptions->start(
                    $organization,
                    $plan,
                    (string) ($metadata['billing_cycle'] ?? 'monthly'),
                    $subscription,
                    isset($session->subscription) && is_string($session->subscription) ? $session->subscription : null,
                );

                if (isset($session->subscription) && is_string($session->subscription)) {
                    try {
                        $this->subscriptions->syncFromStripe($updated, $this->stripe->retrieveSubscription($session->subscription));
                    } catch (Throwable) {
                        // The local period is already set; a later updated event will reconcile.
                    }
                }
            }

            return;
        }

        if (($session->mode ?? null) !== 'payment' || ($session->payment_status ?? null) !== 'paid') {
            return;
        }

        $amount = (float) ($metadata['credit_amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        $this->credits->add(
            $organization,
            $amount,
            __('ui.billing.credit_top_up_line'),
            null,
            'stripe_checkout',
            is_string($session->id ?? null) ? $session->id : null,
        );

        Payment::query()->create([
            'organization_id' => $organization->getKey(),
            'gateway' => Payment::GATEWAY_STRIPE,
            'gateway_reference' => is_string($session->payment_intent ?? null)
                ? $session->payment_intent
                : (is_string($session->id ?? null) ? $session->id : null),
            'amount' => $amount,
            'currency' => strtoupper((string) ($session->currency ?? $organization->currency ?? 'USD')),
            'status' => Payment::STATUS_SUCCEEDED,
            'paid_at' => now(),
            'metadata' => ['source' => 'checkout', 'session_id' => $session->id ?? null],
        ]);

        AuditLog::record('stripe.credit_top_up', $organization, null, (string) $amount);
    }

    private function subscriptionChanged(StripeWebhookEvent $event): void
    {
        $stripeSubscription = $event->data->object;
        $id = is_string($stripeSubscription->id ?? null) ? $stripeSubscription->id : '';

        if ($id === '') {
            return;
        }

        $subscription = Subscription::query()->where('stripe_subscription_id', $id)->first();

        if (! $subscription instanceof Subscription) {
            return;
        }

        $this->subscriptions->syncFromStripe($subscription, $stripeSubscription);
    }

    private function invoicePaid(StripeWebhookEvent $event): void
    {
        $stripeInvoice = $event->data->object;
        $id = is_string($stripeInvoice->id ?? null) ? $stripeInvoice->id : '';

        if ($id === '') {
            return;
        }

        $invoice = Invoice::query()->where('stripe_invoice_id', $id)->first();

        if (! $invoice instanceof Invoice) {
            return;
        }

        $this->invoices->markPaid($invoice, [
            'gateway' => Payment::GATEWAY_STRIPE,
            'reference' => $id,
            'metadata' => ['source' => 'stripe_invoice'],
        ]);
    }

    private function invoiceFailed(StripeWebhookEvent $event): void
    {
        $stripeInvoice = $event->data->object;

        if (! is_string($stripeInvoice->subscription ?? null)) {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeInvoice->subscription)
            ->first();

        if ($subscription instanceof Subscription) {
            $this->subscriptions->markPastDue($subscription);
        }
    }

    /**
     * @return array<string, string>
     */
    private function metadata(object $object): array
    {
        $metadata = $object->metadata ?? null;

        if (is_object($metadata)) {
            $metadata = (array) $metadata;
        }

        if (! is_array($metadata)) {
            return [];
        }

        return array_filter(
            array_map(static fn ($value): string => is_scalar($value) ? (string) $value : '', $metadata),
            static fn (string $value): bool => $value !== '',
        );
    }
}
