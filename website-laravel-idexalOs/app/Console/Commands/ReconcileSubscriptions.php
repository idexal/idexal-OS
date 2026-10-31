<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class ReconcileSubscriptions extends Command
{
    protected $signature = 'subscriptions:reconcile';

    protected $description = 'Close subscriptions whose paid period ended and abandon stale incomplete checkouts';

    public function handle(): int
    {
        $canceled = 0;
        $abandoned = 0;

        Subscription::query()
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_PAST_DUE,
            ])
            ->whereNotNull('canceled_at')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->each(function (Subscription $subscription) use (&$canceled): void {
                $subscription->forceFill([
                    'status' => Subscription::STATUS_CANCELED,
                    'updated_at' => now(),
                ])->save();
                $canceled++;
            });

        Subscription::query()
            ->where('status', Subscription::STATUS_INCOMPLETE)
            ->where('created_at', '<=', now()->subDay())
            ->each(function (Subscription $subscription) use (&$abandoned): void {
                $subscription->forceFill([
                    'status' => Subscription::STATUS_CANCELED,
                    'canceled_at' => $subscription->canceled_at ?? now(),
                    'updated_at' => now(),
                ])->save();
                $abandoned++;
            });

        $this->info("Reconciled subscriptions: {$canceled} ended, {$abandoned} abandoned checkouts.");

        return self::SUCCESS;
    }
}
