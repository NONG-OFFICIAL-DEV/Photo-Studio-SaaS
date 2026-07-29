<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            // Closure form so the nested invoice (and its own nested
            // customer) inherits the SAME tenant_id override the caller
            // passes to Payment::factory()->create(['tenant_id' => ...]) —
            // see OrderFactory/BookingFactory for the same fix.
            'invoice_id' => fn (array $attributes) => Invoice::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'amount' => fake()->randomFloat(2, 50, 1000),
            'method' => PaymentMethod::Cash->value,
            'paid_at' => now()->toDateString(),
            'reference' => null,
            'notes' => null,
        ];
    }
}
