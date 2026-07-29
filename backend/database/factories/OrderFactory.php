<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            // Closure form (not a bare Customer::factory()) so the nested
            // customer inherits the SAME tenant_id override the caller
            // passes to Order::factory()->create(['tenant_id' => ...]) —
            // see BookingFactory for the same fix and why it's needed.
            'customer_id' => fn (array $attributes) => Customer::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'booking_id' => null,
            'status' => OrderStatus::Pending->value,
            'subtotal' => 0,
            'discount_amount' => 0,
            'total' => 0,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => OrderStatus::Confirmed->value]);
    }

    public function inProduction(): static
    {
        return $this->state(['status' => OrderStatus::InProduction->value]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled->value, 'cancelled_reason' => fake()->sentence()]);
    }
}
