<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    protected static int $sequence = 0;

    public function definition(): array
    {
        self::$sequence++;

        return [
            // Closure form so the nested customer inherits the SAME
            // tenant_id override the caller passes to
            // Invoice::factory()->create(['tenant_id' => ...]) — see
            // OrderFactory/BookingFactory for the same fix.
            'customer_id' => fn (array $attributes) => Customer::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'order_id' => null,
            'invoice_number' => 'INV-TEST-'.str_pad((string) self::$sequence, 5, '0', STR_PAD_LEFT),
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 100,
            'amount_paid' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => InvoiceStatus::Sent->value]);
    }

    public function overdue(): static
    {
        return $this->state(['status' => InvoiceStatus::Sent->value, 'due_date' => now()->subDays(5)->toDateString()]);
    }

    public function paid(): static
    {
        return $this->state(['status' => InvoiceStatus::Paid->value, 'amount_paid' => 100]);
    }
}
