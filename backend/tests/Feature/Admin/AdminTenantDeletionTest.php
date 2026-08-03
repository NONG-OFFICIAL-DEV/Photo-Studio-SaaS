<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TelegramMessageLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminTenantDeletionTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_super_admin_can_permanently_delete_a_tenant_and_all_of_its_data(): void
    {
        Storage::fake('public');
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $manager = $this->addUserToTenant($tenant, TenantRole::Manager);

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        TelegramMessageLog::create([
            'tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'customer_name' => $customer->name,
            'type' => 'invoice', 'status' => 'sent', 'sent_by' => $owner->id, 'sent_by_name' => $owner->name,
        ]);

        // Storage: a logo file living under tenants/{id}/...
        $logoPath = "tenants/{$tenant->id}/logo.png";
        Storage::disk('public')->put($logoPath, UploadedFile::fake()->image('logo.png')->get());
        $tenant->update(['logo_path' => $logoPath]);

        // Generate some activity_log / api_log rows tied to this tenant.
        $this->actingAsUser($owner)->putJson('/api/v1/settings', ['name' => 'Renamed Studio'])->assertOk();
        $this->actingAsUser($owner)->postJson('/api/v1/customers', ['name' => 'Another Customer'])->assertCreated();

        $this->assertDatabaseHas('activity_log', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('api_logs', ['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertGreaterThan(0, DB::table('roles')->where('tenant_id', $tenant->id)->count());
        $this->assertGreaterThan(0, DB::table('model_has_roles')->where('tenant_id', $tenant->id)->count());

        $response = $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/delete", ['confirm_name' => $tenant->fresh()->name]);

        $response->assertOk()->assertJsonPath('success', true);

        // The tenant itself is gone (hard delete, not soft delete).
        $this->assertNull(Tenant::withoutGlobalScopes()->withTrashed()->find($tenant->id));

        // Cascade-deleted tables.
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('users', ['id' => $manager->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('telegram_message_logs', ['tenant_id' => $tenant->id]);

        // Explicitly-swept, non-cascading tables.
        $this->assertDatabaseMissing('activity_log', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('api_logs', ['tenant_id' => $tenant->id]);
        $this->assertSame(0, DB::table('roles')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, DB::table('model_has_roles')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, DB::table('model_has_permissions')->where('tenant_id', $tenant->id)->count());

        // Storage cleaned up.
        Storage::disk('public')->assertMissing($logoPath);
        $this->assertEmpty(Storage::disk('public')->allFiles("tenants/{$tenant->id}"));

        // Orphaned notifications for this tenant's (now-deleted) users are gone too.
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $owner->id]);
    }

    public function test_confirmation_name_mismatch_is_rejected_and_nothing_is_deleted(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/delete", ['confirm_name' => 'Wrong Name']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('meta.errors.confirm_name.0', "The typed name does not match this tenant's name.");

        $this->assertNotNull(Tenant::withoutGlobalScopes()->find($tenant->id));
    }

    public function test_a_non_super_admin_cannot_delete_a_tenant(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/delete", ['confirm_name' => $tenant->name])
            ->assertForbidden();

        $this->assertNotNull(Tenant::withoutGlobalScopes()->find($tenant->id));
    }

    public function test_a_platform_audit_log_entry_survives_the_deletion(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantName = $tenant->name;

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/delete", ['confirm_name' => $tenantName])
            ->assertOk();

        $entry = Activity::query()
            ->where('log_name', 'audit')
            ->whereNull('tenant_id')
            ->where('description', 'like', "%{$tenantName}%")
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($superAdmin->id, $entry->causer_id);

        // And it's reachable via the super admin's unfiltered (no tenant_id) audit view.
        $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/audit/log')
            ->assertOk()
            ->assertJsonFragment(['description' => "Tenant \"{$tenantName}\" and all of its data were permanently deleted"]);
    }

    public function test_deleting_one_tenant_does_not_affect_another_tenant(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenantA->id}/delete", ['confirm_name' => $tenantA->name])
            ->assertOk();

        $this->assertNull(Tenant::withoutGlobalScopes()->find($tenantA->id));
        $this->assertNotNull(Tenant::withoutGlobalScopes()->find($tenantB->id));
        $this->assertDatabaseHas('users', ['id' => $ownerB->id]);
        $this->assertDatabaseHas('customers', ['id' => $customerB->id]);
    }
}
