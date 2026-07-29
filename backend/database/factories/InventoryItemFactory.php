<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sku' => null,
            'unit' => 'unit',
            'category' => null,
            'quantity_on_hand' => 0,
            'reorder_threshold' => null,
            'is_active' => true,
        ];
    }
}
