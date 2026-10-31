<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SubscriptionService $subscriptions,
        private readonly NotificationService $notifications,
        private readonly WebhookService $webhooks,
        private readonly CreditService $credits,
        private readonly ReferralService $referrals,
    ) {}

    /**
     * @param  list<array{description: string, quantity?: float|int, unit_price: float|int, metadata?: array<string, mixed>}>  $items
     * @param  array<string, mixed>  $options
     */
    public function create(Organization $organization, array $items, array $options = []): Invoice
    {
        return DB::transaction(function () use ($organization, $items, $options): Invoice {
            $subtotal = 0.0;
            $normalizedItems = [];

            foreach ($items as $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = (float) $item['unit_price'];
                $amount = round($quantity * $unitPrice, 6);
                $subtotal += $amount;

                $normalizedItems[] = [
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                    'metadata' => $item['metadata'] ?? null,
                ];
            }

            $taxRate = (float) ($options['tax_rate'] ?? $this->settings->get('tax_rate', 0));
            $discount = (float) ($options['discount'] ?? 0);
            $tax = round(max(0, $subtotal - $discount) * ($taxRate / 100), 6);
            $total = round($subtotal - $discount + $tax, 6);
            $issuedAt = $options['issued_at'] ?? now();
            $dueAt = $options['due_at'] ?? now()->addDays($this->settings->integer('invoice_due_days', 14));

            $invoice = Invoice::query()->create([
                'organization_id' => $organization->getKey(),
                'subscription_id' => $options['subscription_id'] ?? null,
                'number' => $this->nextNumber(),
                'status' => $options['status'] ?? Invoice::STATUS_OPEN,
                'currency' => $options['currency'] ?? $organization->currency ?? $this->settings->get('default_currency', 'USD'),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'amount_paid' => 0,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
                'period_start' => $options['period_start'] ?? null,
                'period_end' => $options['period_end'] ?? null,
                'notes' => $options['notes'] ?? null,
            ]);

            foreach ($normalizedItems as $item) {
                $invoice->items()->create($item);
            }

            return $invoice->load('items', 'organization');
        });
    }

    public function createPlanInvoice(
        Organization $organization,
        Plan $plan,
        string $billingCycle,
        ?Subscription $subscription = null,
    ): Invoice {
        $periodStart = now();
        $periodEnd = $billingCycle === 'yearly'
            ? $periodStart->copy()->addYearNoOverflow()
            : $periodStart->copy()->addMonthNoOverflow();

        return $this->create(
            $organization,
            [[
                'description' => __('ui.billing.plan_invoice_line', [
                    'plan' => $plan->name,
                    'cycle' => __('ui.billing.cycles.'.$billingCycle),
                ]),
                'quantity' => 1,
                'unit_price' => $plan->priceFor($billingCycle),
                'metadata' => ['plan_id' => $plan->getKey(), 'billing_cycle' => $billingCycle],
            ]],
            [
                'subscription_id' => $subscription?->getKey(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'notes' => __('ui.billing.plan_invoice_note', ['plan' => $plan->name]),
            ],
        );
    }

    public function createCreditTopUpInvoice(Organization $organization, float $amount, ?User $user = null): Invoice
    {
        return $this->create(
            $organization,
            [[
                'description' => __('ui.billing.credit_top_up_line'),
                'quantity' => 1,
                'unit_price' => $amount,
                'metadata' => ['credit_top_up' => $amount],
            ]],
            [
                'notes' => __('ui.billing.credit_top_up_note'),
            ],
        );
    }

    /**
     * @param  array{gateway?: string, reference?: string|null, amount?: float, user_id?: string|null, metadata?: array<string, mixed>}  $data
     */
    public function markPaid(Invoice $invoice, array $data = []): Invoice
    {
        $result = DB::transaction(function () use ($invoice, $data): Invoice {
            $locked = Invoice::query()
                ->with(['subscription.plan', 'organization', 'items'])
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isPaid()) {
                return $locked;
            }

            if ($locked->status === Invoice::STATUS_VOID) {
                return $locked;
            }

            $balance = $locked->balanceDue();
            $amount = isset($data['amount']) ? (float) $data['amount'] : $balance;
            $amount = min($amount, $balance);

            if ($amount <= 0) {
                return $locked;
            }

            Payment::query()->create([
                'invoice_id' => $locked->getKey(),
                'organization_id' => $locked->organization_id,
                'user_id' => $data['user_id'] ?? null,
                'gateway' => $data['gateway'] ?? Payment::GATEWAY_MANUAL,
                'gateway_reference' => $data['reference'] ?? null,
                'amount' => $amount,
                'currency' => $locked->currency,
                'status' => Payment::STATUS_SUCCEEDED,
                'paid_at' => now(),
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->settle($locked, $amount);

            return $locked;
        });

        if ($result->isPaid()) {
            $this->afterPaid($result);
        }

        return $result;
    }

    /**
     * Turn a payment that was already recorded as pending (a bank transfer a
     * customer announced, or a PayPal order captured on return) into a settled
     * invoice, without creating a second payment row for the same money.
     */
    public function confirmPendingPayment(Payment $payment): Invoice
    {
        $payment->loadMissing('invoice');

        $invoice = $payment->invoice;

        if (! $invoice instanceof Invoice) {
            throw new RuntimeException('This payment is not attached to an invoice.');
        }

        $result = DB::transaction(function () use ($payment, $invoice): Invoice {
            $locked = Invoice::query()
                ->with(['subscription.plan', 'organization', 'items'])
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isPaid() || $locked->status === Invoice::STATUS_VOID) {
                return $locked;
            }

            $amount = min((float) $payment->amount, $locked->balanceDue());

            if ($amount <= 0) {
                return $locked;
            }

            $payment->forceFill([
                'status' => Payment::STATUS_SUCCEEDED,
                'amount' => $amount,
                'currency' => $locked->currency,
                'paid_at' => now(),
            ])->save();

            $this->settle($locked, $amount);

            return $locked;
        });

        if ($result->isPaid()) {
            $this->afterPaid($result);
        }

        return $result;
    }

    /**
     * Apply a settled amount to a locked invoice: paid total, status, and the
     * side effects (credit top-up, subscription activation) that follow.
     */
    private function settle(Invoice $locked, float $amount): void
    {
        $newPaid = (float) $locked->amount_paid + $amount;
        $fullyPaid = $newPaid + 0.000001 >= (float) $locked->total;

        $locked->fill([
            'amount_paid' => $newPaid,
            'status' => $fullyPaid ? Invoice::STATUS_PAID : Invoice::STATUS_OPEN,
            'paid_at' => $fullyPaid ? now() : null,
        ])->save();

        if (! $fullyPaid) {
            return;
        }

        $creditTopUp = $locked->items->sum(
            fn ($item): float => (float) data_get($item->metadata, 'credit_top_up', 0),
        );

        if ($creditTopUp > 0) {
            $this->credits->add(
                $locked->organization,
                $creditTopUp,
                __('ui.billing.credit_top_up_line'),
                null,
                'invoice',
                $locked->getKey(),
            );
        }

        if (! $locked->subscription instanceof Subscription) {
            return;
        }

        $subscription = $locked->subscription;

        if (in_array($subscription->status, [
            Subscription::STATUS_INCOMPLETE,
            Subscription::STATUS_PAST_DUE,
            Subscription::STATUS_UNPAID,
        ], true)) {
            $this->subscriptions->start(
                $locked->organization,
                $subscription->plan,
                $subscription->billing_cycle,
                $subscription,
            );
        }

        // A manual renewal invoice carries the next period. Without moving the
        // subscription to it, the daily renewal command would invoice the same
        // period again as soon as this one is paid.
        if ($locked->period_end !== null
            && ($subscription->current_period_end === null
                || $subscription->current_period_end->lt($locked->period_end))) {
            $subscription->forceFill([
                'current_period_start' => $locked->period_start
                    ?? $subscription->current_period_start
                    ?? now(),
                'current_period_end' => $locked->period_end,
            ])->save();
        }
    }

    public function markVoid(Invoice $invoice, ?string $reason = null): Invoice
    {
        if ($invoice->isPaid()) {
            return $invoice;
        }

        $invoice->forceFill([
            'status' => Invoice::STATUS_VOID,
            'notes' => trim(($invoice->notes ?? '')."\n".($reason ?? '')),
        ])->save();

        AuditLog::record('invoice.voided', $invoice->organization, $invoice, $reason);

        return $invoice;
    }

    private function afterPaid(Invoice $invoice): void
    {
        $organization = $invoice->organization;

        $this->notifications->notifyManagers(
            $organization,
            'invoice.paid',
            __('ui.notifications.invoice_paid_title', ['number' => $invoice->number]),
            __('ui.notifications.invoice_paid_body', ['amount' => $invoice->formattedTotal()]),
            route('dashboard.invoices.show', $invoice),
        );

        AuditLog::record(
            'invoice.paid',
            $organization,
            $invoice,
            __('ui.notifications.invoice_paid_title', ['number' => $invoice->number]),
        );

        $this->webhooks->dispatchEvent('invoice.paid', $organization, [
            'invoice' => [
                'id' => $invoice->getKey(),
                'number' => $invoice->number,
                'amount' => (float) $invoice->total,
                'currency' => $invoice->currency,
                'paid_at' => $invoice->paid_at?->toIso8601String(),
            ],
        ]);

        $this->referrals->qualifyForPaidInvoice($invoice);
    }

    private function nextNumber(): string
    {
        $year = now()->format('Y');
        $key = 'invoice:'.$year;
        $prefix = $this->settings->get('invoice_prefix', config('idexal.billing.invoice_prefix', 'IXO'));

        DB::table('number_sequences')->insertOrIgnore([
            'key' => $key,
            'value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();
        $next = ((int) $sequence->value) + 1;

        DB::table('number_sequences')->where('key', $key)->update([
            'value' => $next,
            'updated_at' => now(),
        ]);

        return sprintf('%s-%s-%06d', $prefix, $year, $next);
    }
}
