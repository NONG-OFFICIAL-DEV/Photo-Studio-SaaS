<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\TenantRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Notifications\Booking\BookingReminderCustomerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BookingReminderTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_the_assigned_photographer_is_notified_of_an_upcoming_confirmed_booking(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Booking::factory()->confirmed()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addHours(10),
            'ends_at' => now()->addHours(12),
        ]);

        $this->artisan('bookings:send-reminders')->assertExitCode(0);

        $this->assertSame(1, $photographer->notifications()->count());
        $this->assertSame('booking.upcoming', $photographer->notifications()->first()->data['event']);
        // The Owner wasn't assigned to this booking, so they get nothing.
        $this->assertSame(0, $owner->notifications()->count());
    }

    public function test_the_customer_is_notified_of_an_upcoming_confirmed_booking(): void
    {
        Notification::fake();

        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'email' => 'client@example.test']);

        $booking = Booking::factory()->confirmed()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'starts_at' => now()->addHours(10),
            'ends_at' => now()->addHours(12),
        ]);

        $this->artisan('bookings:send-reminders')->assertExitCode(0);

        Notification::assertSentTo($customer, BookingReminderCustomerNotification::class);
        $this->assertNotNull($booking->fresh()->reminder_sent_at);
    }

    public function test_a_pending_booking_is_not_reminded(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $photographer->id,
            'status' => BookingStatus::Pending,
            'starts_at' => now()->addHours(10),
            'ends_at' => now()->addHours(12),
        ]);

        $this->artisan('bookings:send-reminders')->assertExitCode(0);

        $this->assertSame(0, $photographer->notifications()->count());
    }

    public function test_a_booking_outside_the_reminder_window_is_not_reminded(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Booking::factory()->confirmed()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
        ]);

        $this->artisan('bookings:send-reminders')->assertExitCode(0);

        $this->assertSame(0, $photographer->notifications()->count());
    }

    public function test_a_booking_is_not_reminded_twice(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Booking::factory()->confirmed()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addHours(10),
            'ends_at' => now()->addHours(12),
        ]);

        $this->artisan('bookings:send-reminders')->assertExitCode(0);
        $this->artisan('bookings:send-reminders')->assertExitCode(0);

        $this->assertSame(1, $photographer->notifications()->count());
    }

    public function test_a_tenant_that_disabled_booking_reminders_is_skipped(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['settings' => ['booking_reminders_enabled' => false]]);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $booking = Booking::factory()->confirmed()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addHours(10),
            'ends_at' => now()->addHours(12),
        ]);

        $this->artisan('bookings:send-reminders')->assertExitCode(0);

        $this->assertSame(0, $photographer->notifications()->count());
        // Left null on purpose — re-enabling later should still catch it.
        $this->assertNull($booking->fresh()->reminder_sent_at);
    }
}
