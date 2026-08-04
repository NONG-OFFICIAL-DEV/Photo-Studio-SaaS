<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantRole;
use App\Models\PaymentConfirmation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminPaymentConfirmationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    protected function submitClaim($owner, ?float $amount = 29.99): PaymentConfirmation
    {
        $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/payment-claims', ['claimed_amount' => $amount, 'note' => 'Paid via bank transfer'])
            ->assertCreated();

        return PaymentConfirmation::latest('created_at')->first();
    }

    public function test_super_admin_sees_pending_claims(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_monthly' => 29.99]);
        $this->submitClaim($owner);

        $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/payment-claims')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.tenant_name', $tenant->name);
    }

    public function test_confirming_a_claim_renews_the_subscription_and_links_the_payment(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_monthly' => 29.99]);
        $claim = $this->submitClaim($owner);
        $originalPeriodEnd = $tenant->activeSubscription->current_period_ends_at;

        $response = $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/payment-claims/{$claim->id}/confirm")
            ->assertOk();

        $response->assertJsonPath('data.status', 'confirmed');

        $claim->refresh();
        $this->assertSame('confirmed', $claim->status->value);
        $this->assertSame($superAdmin->id, $claim->reviewed_by);
        $this->assertNotNull($claim->reviewed_at);
        $this->assertNotNull($claim->linked_payment_id);

        $subscription = Subscription::find($tenant->activeSubscription->id);
        $this->assertTrue($subscription->current_period_ends_at->gt($originalPeriodEnd));

        $this->assertDatabaseHas('subscription_payments', [
            'id' => $claim->linked_payment_id,
            'tenant_id' => $tenant->id,
            'recorded_by' => $superAdmin->id,
        ]);
    }

    public function test_rejecting_a_claim_does_not_touch_the_subscription(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $claim = $this->submitClaim($owner);
        $originalPeriodEnd = $tenant->activeSubscription->current_period_ends_at;

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/payment-claims/{$claim->id}/reject", ['note' => 'No matching transfer found'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $claim->refresh();
        $this->assertSame('rejected', $claim->status->value);
        $this->assertSame('No matching transfer found', $claim->review_note);
        $this->assertNull($claim->linked_payment_id);

        $subscription = Subscription::find($tenant->activeSubscription->id);
        $this->assertTrue($subscription->current_period_ends_at->eq($originalPeriodEnd));
    }

    public function test_an_already_reviewed_claim_cannot_be_reviewed_again(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_monthly' => 29.99]);
        $claim = $this->submitClaim($owner);

        $this->actingAsUser($superAdmin)->postJson("/api/v1/admin/payment-claims/{$claim->id}/confirm")->assertOk();

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/payment-claims/{$claim->id}/reject")
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYMENT_CLAIM_ALREADY_REVIEWED');
    }

    public function test_a_non_super_admin_cannot_review_claims(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $claim = $this->submitClaim($owner);

        $this->actingAsUser($owner)->getJson('/api/v1/admin/payment-claims')->assertForbidden();
        $this->actingAsUser($owner)->postJson("/api/v1/admin/payment-claims/{$claim->id}/confirm")->assertForbidden();
    }

    public function test_confirming_one_tenants_claim_does_not_affect_another_tenant(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantA->activeSubscription->plan->update(['price_monthly' => 29.99]);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantBPeriodEnd = $tenantB->activeSubscription->current_period_ends_at;
        $claim = $this->submitClaim($ownerA);

        $this->actingAsUser($superAdmin)->postJson("/api/v1/admin/payment-claims/{$claim->id}/confirm")->assertOk();

        $subscriptionB = Subscription::find($tenantB->activeSubscription->id);
        $this->assertTrue($subscriptionB->current_period_ends_at->eq($tenantBPeriodEnd));
    }
}
