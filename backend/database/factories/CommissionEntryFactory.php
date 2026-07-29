<?php

namespace Database\Factories;

use App\Models\CommissionEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionEntry>
 */
class CommissionEntryFactory extends Factory
{
    protected $model = CommissionEntry::class;

    public function definition(): array
    {
        return [
            'user_id' => fn (array $attributes) => User::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'order_id' => null,
            'amount' => fake()->randomFloat(2, 10, 500),
            'earned_date' => now()->toDateString(),
            'notes' => fake()->sentence(),
        ];
    }
}
