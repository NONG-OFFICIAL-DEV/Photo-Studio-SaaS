<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceStatus;
use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Notifications\Invoice\InvoiceDueSoonCustomerNotification;
use App\Notifications\Invoice\InvoiceOverdueCustomerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InvoiceReminderTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_the_owner_is_notified_when_an_invoice_is_due_soon(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Invoice::factory()->sent()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->artisan('invoices:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame('invoice.due_soon', $owner->notifications()->first()->data['event']);
    }

    public function test_the_owner_is_notified_when_an_invoice_is_overdue(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame('invoice.overdue', $owner->notifications()->first()->data['event']);
    }

    public function test_the_customer_is_notified_for_both_due_soon_and_overdue_invoices(): void
    {
        Notification::fake();

        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'email' => 'client@example.test']);

        $dueSoon = Invoice::factory()->sent()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'due_date' => now()->addDay()->toDateString(),
        ]);
        $overdue = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:send-payment-reminders')->assertExitCode(0);

        Notification::assertSentTo($customer, InvoiceDueSoonCustomerNotification::class);
        Notification::assertSentTo($customer, InvoiceOverdueCustomerNotification::class);
        $this->assertNotNull($dueSoon->fresh()->due_soon_reminder_sent_at);
        $this->assertNotNull($overdue->fresh()->overdue_reminder_sent_at);
    }

    public function test_a_paid_invoice_is_not_reminded(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Invoice::factory()->paid()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(0, $owner->notifications()->count());
    }

    public function test_an_invoice_far_from_its_due_date_is_not_reminded(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Invoice::factory()->sent()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->artisan('invoices:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(0, $owner->notifications()->count());
    }

    public function test_an_invoice_is_not_reminded_twice(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:send-payment-reminders')->assertExitCode(0);
        $this->artisan('invoices:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_a_tenant_that_disabled_invoice_reminders_is_skipped(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['settings' => ['invoice_reminders_enabled' => false]]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $overdue = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(0, $owner->notifications()->count());
        $this->assertNull($overdue->fresh()->overdue_reminder_sent_at);
    }
}
