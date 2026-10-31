<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTopUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_credit_top_up_invoice_adds_the_credit_exactly_once(): void
    {
        $organization = Organization::factory()->create();
        $invoices = app(InvoiceService::class);

        $invoice = $invoices->createCreditTopUpInvoice($organization, 25);

        $this->assertSame(0.0, (float) $organization->fresh()->credit_balance);

        $invoices->markPaid($invoice);

        $organization->refresh();
        $this->assertSame(25.0, (float) $organization->credit_balance);
        $this->assertDatabaseHas('credit_transactions', [
            'organization_id' => $organization->getKey(),
            'amount' => 25,
            'reference_type' => 'invoice',
            'reference_id' => $invoice->getKey(),
        ]);

        $invoices->markPaid($invoice->fresh());

        $this->assertSame(25.0, (float) $organization->fresh()->credit_balance);
        $this->assertDatabaseCount('credit_transactions', 1);
    }
}
