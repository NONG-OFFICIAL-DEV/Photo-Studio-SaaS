<?php

namespace Tests\Feature\Package;

use App\Enums\TenantRole;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class PackageQuickCopyTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_fetch_the_package_summary_text(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wedding Package',
            'override_price' => 500,
        ]);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/packages/{$package->id}/summary-text")
            ->assertOk()
            ->assertJsonPath('data.text', fn ($text) => str_contains($text, 'Wedding Package')
                && str_contains($text, 'Price: $500.00'));
    }

    public function test_a_tenant_cannot_fetch_another_tenants_package_summary_text(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $packageB = Package::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/packages/{$packageB->id}/summary-text")
            ->assertNotFound();
    }

    public function test_owner_can_fetch_the_package_image(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->get("/api/v1/packages/{$package->id}/image")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_a_tenant_cannot_fetch_another_tenants_package_image(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $packageB = Package::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->get("/api/v1/packages/{$packageB->id}/image")
            ->assertNotFound();
    }

    public function test_the_image_renders_fine_with_no_description_and_no_components(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $package = Package::factory()->create(['tenant_id' => $tenant->id, 'description' => null]);

        $this->actingAsUser($owner)
            ->get("/api/v1/packages/{$package->id}/image")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_the_image_renders_khmer_text_without_erroring(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'កញ្ចប់អាពាហ៍ពិពាហ៍',
            'description' => 'ការថតរូបពេញមួយថ្ងៃ',
        ]);

        $this->actingAsUser($owner)
            ->get("/api/v1/packages/{$package->id}/image")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }
}
