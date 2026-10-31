<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LatinNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The platform serves a global audience: numbers are always plain ASCII
     * digits with latin separators, never Arabic-Indic (Egyptian) numerals,
     * no matter which language the page is rendered in.
     */
    private function assertPlainNumbers(string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/^[ -~]*$/',
            $value,
            "Numbers must stay plain ASCII, got: {$value}",
        );
    }

    public function test_helpers_use_plain_numbers_in_arabic(): void
    {
        App::setLocale('ar');

        $this->assertPlainNumbers(money(0));
        $this->assertPlainNumbers(money(1234.5));
        $this->assertPlainNumbers(compact_number(9073504));
        $this->assertSame('9,073,504', compact_number(9073504));
        $this->assertStringContainsString('0.00', money(0));
        $this->assertStringContainsString('1,234.50', money(1234.5));
    }

    public function test_invoice_and_plan_amounts_use_plain_numbers_in_arabic(): void
    {
        App::setLocale('ar');

        $invoice = new Invoice(['total' => 1234.5, 'currency' => 'USD', 'balance' => 100]);
        $this->assertPlainNumbers($invoice->formattedTotal());
        $this->assertPlainNumbers($invoice->formattedBalance());

        $plan = new Plan(['currency' => 'USD', 'price_monthly' => 1234.5, 'price_yearly' => 9999]);
        $this->assertPlainNumbers($plan->formattedPrice('monthly'));
        $this->assertPlainNumbers($plan->formattedPrice('yearly'));
    }

    public function test_arabic_pages_never_render_arabic_indic_digits(): void
    {
        $response = $this->withSession(['locale' => 'ar'])->get('/pricing');
        $response->assertOk();

        $this->assertDoesNotMatchRegularExpression(
            '/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u',
            (string) $response->getContent(),
            'Arabic-Indic digits leaked into the pricing page',
        );
        $this->assertStringNotContainsString('٬', (string) $response->getContent());
        $this->assertStringNotContainsString('٫', (string) $response->getContent());
    }

    public function test_arabic_admin_pages_never_render_arabic_indic_digits(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->withSession(['locale' => 'ar'])->get('/admin/gateway');
        $response->assertOk();

        $this->assertDoesNotMatchRegularExpression(
            '/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u',
            (string) $response->getContent(),
            'Arabic-Indic digits leaked into the admin gateway page',
        );
    }
}
