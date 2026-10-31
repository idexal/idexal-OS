<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\InvoiceService;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Throwable;

class BillingRenewSubscriptions extends Command
{
    protected $signature = 'billing:renew-subscriptions';

    protected $description = 'Renew manual subscriptions and create invoices for the next billing period';

    public function handle(
        InvoiceService $invoices,
        SubscriptionService $subscriptions,
        StripeService $stripe,
    ): int {
        $renewed = 0;
        $invoiced = 0;

        Subscription::query()
            ->with(['organization', 'plan'])
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING, Subscription::STATUS_PAST_DUE])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->orderBy('current_period_end')
            ->each(function (Subscription $subscription) use ($invoices, $subscriptions, $stripe, &$renewed, &$invoiced): void {
                $organization = $subscription->organization;
                $plan = $subscription->plan;

                if ($organization === null || $plan === null) {
                    return;
                }

                // Cancel-at-period-end leaves Stripe's status active until the
                // period closes; never invoice another period after it ended.
                if ($subscription->hasEnded()) {
                    $subscription->forceFill(['status' => Subscription::STATUS_CANCELED])->save();

                    return;
                }

                if ($plan->isFree()) {
                    $subscriptions->start($organization, $plan, $subscription->billing_cycle, $subscription);
                    $renewed++;

                    return;
                }

                if (filled($subscription->stripe_subscription_id) && $stripe->enabled()) {
                    try {
                        $subscriptions->syncFromStripe($subscription, $stripe->retrieveSubscription($subscription->stripe_subscription_id));
                    } catch (Throwable $exception) {
                        report($exception);
                    }

                    return;
                }

                $openInvoice = $organization->invoices()
                    ->where('subscription_id', $subscription->getKey())
                    ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_DRAFT])
                    ->exists();

                if ($openInvoice) {
                    if ($subscription->current_period_end->addDays((int) config('idexal.billing.grace_days', 3))->isPast()) {
                        $subscriptions->markPastDue($subscription);
                    }

                    return;
                }

                $invoices->createPlanInvoice($organization, $plan, $subscription->billing_cycle, $subscription);
                $invoiced++;
            });

        $this->info(__('ui.admin.renewal_result', ['renewed' => $renewed, 'invoiced' => $invoiced]));

        return self::SUCCESS;
    }
}
