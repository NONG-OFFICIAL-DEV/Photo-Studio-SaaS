<?php

namespace Tests\Feature\Invoice;

use App\Enums\TenantRole;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_a_draft_invoice_can_be_sent(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');
    }

    public function test_a_non_draft_invoice_cannot_be_sent_again(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/send")
            ->assertStatus(422);
    }

    public function test_a_full_payment_marks_the_invoice_paid(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id, 'total' => 100, 'amount_paid' => 0]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => 100,
                'method' => 'cash',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount_paid', 100)
            ->assertJsonPath('data.balance_due', 0);
    }

    public function test_a_partial_payment_marks_the_invoice_partially_paid(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id, 'total' => 100, 'amount_paid' => 0]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => 40,
                'method' => 'bank_transfer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.amount_paid', 40)
            ->assertJsonPath('data.balance_due', 60);
    }

    public function test_a_payment_cannot_exceed_the_remaining_balance(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id, 'total' => 100, 'amount_paid' => 0]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 150, 'method' => 'cash'])
            ->assertStatus(422);
    }

    public function test_payments_cannot_be_recorded_on_a_draft_invoice(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 10, 'method' => 'cash'])
            ->assertStatus(422);
    }

    public function test_deleting_a_payment_recalculates_the_invoice_status(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id, 'total' => 100, 'amount_paid' => 0]);

        $paymentId = $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 100, 'method' => 'cash'])
            ->assertJsonPath('data.status', 'paid')
            ->json('data.payments.0.id');

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/invoices/{$invoice->id}/payments/{$paymentId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.amount_paid', 0);
    }

    public function test_a_paid_invoice_cannot_be_voided(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->paid()->create(['tenant_id' => $tenant->id, 'total' => 100]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/void", ['reason' => 'test'])
            ->assertStatus(422);
    }

    public function test_a_sent_invoice_can_be_voided_with_a_reason(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/void", ['reason' => 'Customer cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.voided_reason', 'Customer cancelled');
    }

    public function test_voiding_requires_a_reason(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/void", [])
            ->assertStatus(422);
    }

    public function test_overdue_command_marks_past_due_sent_invoices_as_overdue(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $overdue = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id]);
        $current = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id, 'due_date' => now()->addDays(5)->toDateString()]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame('overdue', $overdue->fresh()->status->value);
        $this->assertSame('sent', $current->fresh()->status->value);
    }

    public function test_payment_status_changes_are_recorded_in_activity_log(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id, 'total' => 100]);

        $this->actingAsUser($owner)->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 100, 'method' => 'cash']);

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $invoice->id,
            'log_name' => 'invoice',
            'tenant_id' => $tenant->id,
        ]);
    }
}
