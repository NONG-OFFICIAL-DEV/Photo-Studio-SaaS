<?php

namespace Tests\Feature\Customer;

use App\Enums\TenantRole;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class CustomerImportExportTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_exports_customers_as_csv(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Export Me', 'email' => 'export@example.test']);

        $response = $this->actingAsUser($owner)
            ->get('/api/v1/customers/export?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Export Me', $content);
        $this->assertStringContainsString('export@example.test', $content);
    }

    public function test_it_exports_customers_as_xlsx_by_default(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->get('/api/v1/customers/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_only_includes_the_current_tenants_customers(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Customer::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Tenant A Customer']);
        Customer::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Tenant B Customer']);

        $content = $this->actingAsUser($ownerA)
            ->get('/api/v1/customers/export?format=csv')
            ->streamedContent();

        $this->assertStringContainsString('Tenant A Customer', $content);
        $this->assertStringNotContainsString('Tenant B Customer', $content);
    }

    public function test_it_imports_valid_rows_and_reports_invalid_ones(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $csv = "name,email,phone,gender\n"
            ."Kim Sopheak,kim@example.test,011222333,male\n"
            .",bad@example.test,022333444,male\n"
            ."Ly Dara,ly@example.test,033444555,other\n";

        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/customers/import', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.failures.0.row', 3)
            ->assertJsonPath('data.failures.0.attribute', 'name');

        $this->assertDatabaseHas('customers', ['email' => 'kim@example.test', 'tenant_id' => $owner->tenant_id]);
        $this->assertDatabaseHas('customers', ['email' => 'ly@example.test', 'tenant_id' => $owner->tenant_id]);
        $this->assertDatabaseMissing('customers', ['email' => 'bad@example.test']);
    }

    public function test_imported_customers_are_scoped_to_the_importing_tenant(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $csv = "name,email\nImported Person,imported@example.test\n";
        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/customers/import', ['file' => $file])
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'email' => 'imported@example.test',
            'tenant_id' => $tenant->id,
        ]);
    }
}
