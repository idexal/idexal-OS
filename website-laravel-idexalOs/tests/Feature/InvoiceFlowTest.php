<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_organization_member_can_download_an_arabic_invoice_pdf(): void
    {
        [$user, $organization] = $this->workspace();
        $invoice = $this->invoice($organization);
        $invoice->items()->create([
            'description' => 'اشتراك شهري',
            'quantity' => 1,
            'unit_price' => 19,
            'amount' => 19,
        ]);

        $response = $this->actingAs($user)->get("/dashboard/invoices/{$invoice->getKey()}/download");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_member_cannot_download_another_organizations_invoice(): void
    {
        [$user] = $this->workspace();
        $otherInvoice = Invoice::factory()->create();

        $this->actingAs($user)
            ->get("/dashboard/invoices/{$otherInvoice->getKey()}/download")
            ->assertNotFound();
    }

    public function test_an_admin_can_mark_an_invoice_as_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($admin)
            ->get("/admin/invoices/{$invoice->getKey()}")
            ->assertOk();

        $this->actingAs($admin)
            ->get("/admin/invoices/{$invoice->getKey()}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->post("/admin/invoices/{$invoice->getKey()}/paid", ['amount' => 19])
            ->assertRedirect("/admin/invoices/{$invoice->getKey()}");

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(19.0, (float) $invoice->amount_paid);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->getKey(),
            'gateway' => 'manual',
            'status' => 'succeeded',
        ]);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function workspace(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        return [$user, $organization];
    }

    private function invoice(Organization $organization): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $organization->getKey(),
            'subtotal' => 19,
            'total' => 19,
        ]);
    }
}
