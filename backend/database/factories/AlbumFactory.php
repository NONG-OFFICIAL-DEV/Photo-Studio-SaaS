<?php

namespace Database\Factories;

use App\Enums\AlbumStatus;
use App\Models\Album;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Album>
 */
class AlbumFactory extends Factory
{
    protected $model = Album::class;

    public function definition(): array
    {
        return [
            // Closure form so the nested customer inherits the SAME
            // tenant_id override the caller passes to
            // Album::factory()->create(['tenant_id' => ...]) — see
            // OrderFactory/BookingFactory for the same fix.
            'customer_id' => fn (array $attributes) => Customer::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'order_id' => null,
            'name' => fake()->words(3, true).' Album',
            'description' => fake()->optional()->sentence(),
            'status' => AlbumStatus::Draft->value,
            'expected_photo_count' => fake()->numberBetween(20, 200),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => AlbumStatus::InProgress->value]);
    }

    public function ready(): static
    {
        return $this->state(['status' => AlbumStatus::Ready->value]);
    }

    public function delivered(): static
    {
        return $this->state(['status' => AlbumStatus::Delivered->value, 'delivered_at' => now()]);
    }
}
