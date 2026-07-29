<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        return [
            // Closure form so the nested user inherits the SAME tenant_id
            // override the caller passes to
            // AttendanceRecord::factory()->create(['tenant_id' => ...]) —
            // see OrderFactory/BookingFactory for the same fix.
            'user_id' => fn (array $attributes) => User::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'date' => now()->toDateString(),
            'clock_in_at' => null,
            'clock_out_at' => null,
            'status' => 'present',
        ];
    }
}
