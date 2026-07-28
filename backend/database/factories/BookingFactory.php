<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+30 days');
        $endsAt = (clone $startsAt)->modify('+2 hours');

        return [
            // A plain Customer::factory() relation wouldn't inherit the
            // tenant_id override callers pass to Booking::factory()->create()
            // — Laravel doesn't cascade parent overrides into nested
            // factories. This closure form does: it's evaluated after
            // overrides are merged in, so $attributes['tenant_id'] is the
            // caller's tenant, not null.
            'customer_id' => fn (array $attributes) => Customer::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'type' => fake()->randomElement(BookingType::cases())->value,
            'title' => fake()->sentence(3),
            'location_type' => 'studio',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => BookingStatus::Pending->value,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => BookingStatus::Confirmed->value]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => BookingStatus::Cancelled->value, 'cancelled_reason' => fake()->sentence()]);
    }
}
