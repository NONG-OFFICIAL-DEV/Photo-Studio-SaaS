<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'category_id' => null,
            'amount' => fake()->randomFloat(2, 10, 2000),
            'expense_date' => now()->toDateString(),
            'vendor' => fake()->company(),
            'payment_method' => PaymentMethod::Cash->value,
            'notes' => fake()->sentence(),
        ];
    }
}
