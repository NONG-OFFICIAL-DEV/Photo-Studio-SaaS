<?php

namespace Tests\Feature\Invoice;

use App\Enums\TenantRole;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InvoiceTenantDefaultsTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_invoice_uses_tenant_default_tax_rate_and_due_days_when_omitted(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['settings' => ['default_tax_rate' => 7, 'default_due_days' => 5]]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'items' => [['name' => 'Shoot', 'unit_price' => 100, 'quantity' => 1]],
            ])
            ->assertCreated();

        // subtotal 100, tax 7% => tax_amount 7, total 107
        $response->assertJsonPath('data.tax_amount', 7)
            ->assertJsonPath('data.total', 107);

        $issueDate = \Carbon\Carbon::parse($response->json('data.issue_date'));
        $dueDate = \Carbon\Carbon::parse($response->json('data.due_date'));
        $this->assertSame(5, (int) $issueDate->diffInDays($dueDate));
    }

    public function test_explicit_tax_rate_and_due_date_override_tenant_defaults(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['settings' => ['default_tax_rate' => 7, 'default_due_days' => 5]]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'tax_rate' => 0,
                'due_date' => now()->addDays(60)->toDateString(),
                'items' => [['name' => 'Shoot', 'unit_price' => 100, 'quantity' => 1]],
            ])
            ->assertCreated();

        $response->assertJsonPath('data.tax_amount', 0);
        $this->assertSame(
            now()->addDays(60)->toDateString(),
            \Carbon\Carbon::parse($response->json('data.due_date'))->toDateString()
        );
    }

    public function test_invoice_number_uses_tenant_configured_prefix(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['settings' => ['invoice_prefix' => 'ZZZ-']]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'items' => [['name' => 'Shoot', 'unit_price' => 100, 'quantity' => 1]],
            ])
            ->assertCreated();

        $this->assertStringStartsWith('ZZZ-', $response->json('data.invoice_number'));
    }
}
