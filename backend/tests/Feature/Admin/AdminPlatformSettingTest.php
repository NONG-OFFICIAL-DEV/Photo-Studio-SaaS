<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantRole;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminPlatformSettingTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_super_admin_can_view_platform_settings(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/platform-settings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['khqr_image_url', 'bank_name', 'bank_account_name', 'bank_account_number', 'payment_instructions']]);
    }

    public function test_super_admin_can_update_bank_details(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAsUser($superAdmin)
            ->putJson('/api/v1/admin/platform-settings', [
                'bank_name' => 'ABA Bank',
                'bank_account_name' => 'Photo Studio SaaS Co Ltd',
                'bank_account_number' => '000123456',
                'payment_instructions' => 'Include your studio name as the transfer reference.',
            ])
            ->assertOk()
            ->assertJsonPath('data.bank_name', 'ABA Bank')
            ->assertJsonPath('data.bank_account_number', '000123456');

        $this->assertSame('ABA Bank', PlatformSetting::current()->bank_name);
    }

    public function test_super_admin_can_upload_a_khqr_image(): void
    {
        Storage::fake('public');
        $superAdmin = $this->superAdmin();

        $response = $this->actingAsUser($superAdmin)
            ->post('/api/v1/admin/platform-settings/khqr', ['khqr_image' => UploadedFile::fake()->image('khqr.png')])
            ->assertOk();

        $this->assertNotNull($response->json('data.khqr_image_url'));
        $path = PlatformSetting::current()->khqr_image_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_uploading_a_new_khqr_image_replaces_the_previous_one(): void
    {
        Storage::fake('public');
        $superAdmin = $this->superAdmin();

        $this->actingAsUser($superAdmin)
            ->post('/api/v1/admin/platform-settings/khqr', ['khqr_image' => UploadedFile::fake()->image('first.png')])
            ->assertOk();
        $firstPath = PlatformSetting::current()->khqr_image_path;

        $this->actingAsUser($superAdmin)
            ->post('/api/v1/admin/platform-settings/khqr', ['khqr_image' => UploadedFile::fake()->image('second.png')])
            ->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertNotSame($firstPath, PlatformSetting::current()->khqr_image_path);
    }

    public function test_a_non_super_admin_cannot_view_or_manage_platform_settings(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)->getJson('/api/v1/admin/platform-settings')->assertForbidden();
        $this->actingAsUser($owner)->putJson('/api/v1/admin/platform-settings', ['bank_name' => 'X'])->assertForbidden();
    }
}
