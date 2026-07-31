<?php

namespace Tests\Feature\Settings;

use App\Enums\TenantRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_view_settings_with_defaults(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.settings.invoice_prefix', 'INV-')
            ->assertJsonPath('data.settings.default_tax_rate', 0)
            ->assertJsonPath('data.settings.default_due_days', 14);
    }

    public function test_owner_can_update_company_invoice_and_theme_fields(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/settings', [
                'name' => 'Acme Photo Studio',
                'phone' => '012-345-678',
                'invoice_prefix' => 'ACME-',
                'default_tax_rate' => 8,
                'default_due_days' => 30,
                'invoice_footer' => 'Thank you for your business!',
                'primary_color' => '#123ABC',
                'secondary_color' => '#ABCDEF',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Photo Studio')
            ->assertJsonPath('data.phone', '012-345-678')
            ->assertJsonPath('data.settings.invoice_prefix', 'ACME-')
            ->assertJsonPath('data.settings.default_tax_rate', 8)
            ->assertJsonPath('data.settings.default_due_days', 30)
            ->assertJsonPath('data.settings.invoice_footer', 'Thank you for your business!')
            ->assertJsonPath('data.settings.primary_color', '#123ABC')
            ->assertJsonPath('data.settings.secondary_color', '#ABCDEF');
    }

    public function test_updating_settings_partially_preserves_other_keys(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['settings' => ['invoice_prefix' => 'OLD-', 'default_tax_rate' => 5]]);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/settings', ['default_due_days' => 21])
            ->assertOk()
            ->assertJsonPath('data.settings.invoice_prefix', 'OLD-')
            ->assertJsonPath('data.settings.default_tax_rate', 5)
            ->assertJsonPath('data.settings.default_due_days', 21);
    }

    public function test_invalid_color_is_rejected(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/settings', ['primary_color' => 'not-a-color'])
            ->assertUnprocessable();
    }

    public function test_manager_can_manage_settings(): void
    {
        [, $manager] = $this->createTenantWithUser(TenantRole::Manager);

        $this->actingAsUser($manager)
            ->putJson('/api/v1/settings', ['name' => 'Managed Studio'])
            ->assertOk();
    }

    public function test_cashier_cannot_view_or_update_settings(): void
    {
        [, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);

        $this->actingAsUser($cashier)->getJson('/api/v1/settings')->assertForbidden();
        $this->actingAsUser($cashier)->putJson('/api/v1/settings', ['name' => 'X'])->assertForbidden();
    }

    public function test_cashier_cannot_export_data(): void
    {
        [, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);

        $this->actingAsUser($cashier)
            ->get('/api/v1/settings/export')
            ->assertForbidden();
    }

    public function test_owner_can_export_data_as_zip(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)->get('/api/v1/settings/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');
    }

    public function test_owner_can_upload_a_qr_payment_image(): void
    {
        Storage::fake('public');

        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->assertNull($tenant->qr_payment_path);

        $response = $this->actingAsUser($owner)
            ->post('/api/v1/settings/qr-payment', ['qr_payment' => UploadedFile::fake()->image('qr.png')])
            ->assertOk();

        $this->assertNotNull($response->json('data.qr_payment_url'));
        $this->assertNotNull($tenant->fresh()->qr_payment_path);
    }

    public function test_uploading_a_qr_payment_image_replaces_the_previous_one(): void
    {
        Storage::fake('public');

        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner)
            ->post('/api/v1/settings/qr-payment', ['qr_payment' => UploadedFile::fake()->image('first.png')])
            ->assertOk();
        $firstPath = $tenant->fresh()->qr_payment_path;

        $this->actingAsUser($owner)
            ->post('/api/v1/settings/qr-payment', ['qr_payment' => UploadedFile::fake()->image('second.png')])
            ->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertNotSame($firstPath, $tenant->fresh()->qr_payment_path);
    }

    public function test_a_non_image_qr_payment_upload_is_rejected(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->post('/api/v1/settings/qr-payment', ['qr_payment' => UploadedFile::fake()->create('qr.pdf', 100)])
            ->assertUnprocessable();
    }

    public function test_cashier_cannot_upload_a_qr_payment_image(): void
    {
        [, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);

        $this->actingAsUser($cashier)
            ->post('/api/v1/settings/qr-payment', ['qr_payment' => UploadedFile::fake()->image('qr.png')])
            ->assertForbidden();
    }

    public function test_settings_are_isolated_per_tenant(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($ownerA)
            ->putJson('/api/v1/settings', ['invoice_prefix' => 'A-PREFIX-'])
            ->assertOk();

        $this->actingAsUser($ownerB)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.settings.invoice_prefix', 'INV-');

        $this->assertSame('A-PREFIX-', $tenantA->fresh()->setting('invoice_prefix'));
        $this->assertSame('INV-', $tenantB->fresh()->setting('invoice_prefix'));
    }
}
