<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_renewal_invoice_advances_the_period_and_stops_duplicate_invoices(): void
    {
        $plan = Plan::factory()->create([
            'slug' => 'pro',
            'price_monthly' => 49,
            'is_active' => true,
            'is_public' => true,
        ]);
        $organization = Organization::factory()->create();
        $subscription = Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        $this->artisan('billing:renew-subscriptions')->assertSuccessful();

        $invoice = Invoice::query()->where('subscription_id', $subscription->getKey())->sole();
        $this->assertFalse($invoice->isPaid());

        app(InvoiceService::class)->markPaid($invoice);

        $subscription->refresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->current_period_end->isFuture());

        $this->artisan('billing:renew-subscriptions')->assertSuccessful();

        $this->assertSame(1, Invoice::query()->where('subscription_id', $subscription->getKey())->count());
    }
}
