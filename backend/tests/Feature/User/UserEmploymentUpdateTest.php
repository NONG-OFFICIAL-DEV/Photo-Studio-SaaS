<?php

namespace Tests\Feature\User;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class UserEmploymentUpdateTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_update_an_employees_pay_profile(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/users/{$employee->id}", [
                'pay_type' => 'hourly',
                'base_pay' => 25,
                'commission_rate' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.pay_type', 'hourly')
            ->assertJsonPath('data.base_pay', 25)
            ->assertJsonPath('data.commission_rate', 10);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'pay_type' => 'hourly']);
    }

    public function test_photographer_cannot_update_another_employees_pay_profile(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $other = $this->addUserToTenant($tenant, TenantRole::Editor);

        $this->actingAsUser($photographer)
            ->putJson("/api/v1/users/{$other->id}", ['pay_type' => 'hourly'])
            ->assertForbidden();
    }

    public function test_a_commission_rate_over_100_is_rejected(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/users/{$employee->id}", ['commission_rate' => 150])
            ->assertStatus(422);
    }

    public function test_a_manager_cannot_update_a_user_from_another_tenant(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $employeeB = $this->addUserToTenant($tenantB, TenantRole::Photographer);

        // The {user} route model binding is itself tenant-scoped (TenantScope),
        // so a cross-tenant id resolves to nothing before the policy even runs.
        $this->actingAsUser($ownerA)
            ->putJson("/api/v1/users/{$employeeB->id}", ['pay_type' => 'hourly'])
            ->assertNotFound();
    }
}
