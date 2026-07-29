<?php

namespace Database\Factories;

use App\Models\PayrollEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollEntry>
 */
class PayrollEntryFactory extends Factory
{
    protected $model = PayrollEntry::class;

    public function definition(): array
    {
        return [
            'user_id' => fn (array $attributes) => User::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'period_label' => 'July 2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'base_pay' => 1000,
            'commission_total' => 0,
            'deductions' => 0,
            'net_pay' => 1000,
            'status' => 'draft',
        ];
    }
}
