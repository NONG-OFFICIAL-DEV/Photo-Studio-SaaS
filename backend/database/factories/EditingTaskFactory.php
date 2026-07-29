<?php

namespace Database\Factories;

use App\Enums\EditingStatus;
use App\Models\EditingTask;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditingTask>
 */
class EditingTaskFactory extends Factory
{
    protected $model = EditingTask::class;

    public function definition(): array
    {
        return [
            'order_id' => fn (array $attributes) => Order::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'assigned_user_id' => null,
            'status' => EditingStatus::Pending->value,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => EditingStatus::InProgress->value]);
    }

    public function completed(): static
    {
        return $this->state(['status' => EditingStatus::Completed->value, 'completed_at' => now()]);
    }
}
