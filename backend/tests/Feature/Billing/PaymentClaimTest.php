<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentConfirmationStatus;
use App\Enums\TenantRole;
use App\Models\PaymentConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class PaymentClaimTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_owner_can_submit_a_payment_claim(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/payment-claims', [
                'claimed_amount' => 29.99,
                'note' => 'Transferred via ABA, ref: my studio name',
            ])
            ->assertCreated();

        $response->assertJsonPath('data.status', PaymentConfirmationStatus::Pending->value)
            ->assertJsonPath('data.claimed_amount', 29.99);

        $this->assertDatabaseHas('payment_confirmations', [
            'tenant_id' => $tenant->id,
            'submitted_by' => $owner->id,
            'status' => 'pending',
        ]);
    }

    public function test_owner_can_submit_a_payment_claim_with_a_receipt(): void
    {
        Storage::fake('public');
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->post('/api/v1/billing/payment-claims', [
                'claimed_amount' => 29.99,
                'note' => 'Paid',
                'receipt' => UploadedFile::fake()->image('receipt.png'),
            ])
            ->assertCreated();

        $claim = PaymentConfirmation::first();
        $this->assertNotNull($claim->receipt_path);
        Storage::disk('public')->assertExists($claim->receipt_path);
    }

    public function test_submitting_a_claim_notifies_every_super_admin(): void
    {
        $superAdminA = $this->superAdmin();
        $superAdminB = $this->superAdmin();
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/payment-claims', ['claimed_amount' => 29.99, 'note' => 'Paid'])
            ->assertCreated();

        $this->assertSame(1, $superAdminA->notifications()->count());
        $this->assertSame('payment.claimed', $superAdminA->notifications()->first()->data['event']);
        $this->assertSame(1, $superAdminB->notifications()->count());

        // The tenant owner who submitted it isn't notified about their own claim.
        $this->assertSame(0, $owner->notifications()->count());
    }

    public function test_a_tenant_cannot_submit_a_second_claim_while_one_is_pending(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/payment-claims', ['claimed_amount' => 29.99, 'note' => 'First'])
            ->assertCreated();

        $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/payment-claims', ['claimed_amount' => 29.99, 'note' => 'Second'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYMENT_CLAIM_ALREADY_PENDING');
    }

    public function test_a_claim_without_an_amount_is_rejected(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/payment-claims', ['note' => 'Paid, forgot the amount'])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.claimed_amount.0', 'The claimed amount field is required.');
    }

    public function test_a_role_without_billing_permission_cannot_submit_a_claim(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/billing/payment-claims', ['note' => 'Paid'])
            ->assertForbidden();
    }

    public function test_billing_shows_the_pending_claim_when_one_exists(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)->getJson('/api/v1/billing')->assertJsonPath('data.pending_payment_claim', null);

        $this->actingAsUser($owner)->postJson('/api/v1/billing/payment-claims', ['claimed_amount' => 50, 'note' => 'Paid'])->assertCreated();

        $this->actingAsUser($owner)
            ->getJson('/api/v1/billing')
            ->assertOk()
            ->assertJsonPath('data.pending_payment_claim.status', 'pending')
            ->assertJsonPath('data.pending_payment_claim.claimed_amount', 50);
    }
}
