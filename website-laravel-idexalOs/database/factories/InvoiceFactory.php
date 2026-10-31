<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = 19;

        return [
            'organization_id' => Organization::factory(),
            'number' => 'IXO-TEST-'.fake()->unique()->numerify('######'),
            'status' => Invoice::STATUS_OPEN,
            'currency' => 'USD',
            'subtotal' => $subtotal,
            'tax' => 0,
            'discount' => 0,
            'total' => $subtotal,
            'amount_paid' => 0,
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_PAID,
            'amount_paid' => $attributes['total'] ?? 19,
            'paid_at' => now(),
        ]);
    }
}
