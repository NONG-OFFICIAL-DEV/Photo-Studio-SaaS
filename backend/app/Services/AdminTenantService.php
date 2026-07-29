<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminTenantService extends BaseService
{
    public function __construct(
        protected TenantRepositoryInterface $tenants,
        protected SubscriptionService $subscriptions,
    ) {
        parent::__construct($tenants);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->tenants->adminPaginate($filters);
    }

    public function show(string $id): Tenant
    {
        return $this->tenants->findOrFail($id)->load('activeSubscription.plan');
    }

    public function suspend(Tenant $tenant): Tenant
    {
        $tenant = $this->tenants->update($tenant, ['is_active' => false]);

        activity('audit')->performedOn($tenant)->tap(fn ($a) => $a->tenant_id = $tenant->id)->log("Tenant \"{$tenant->name}\" suspended");

        return $tenant;
    }

    public function activate(Tenant $tenant): Tenant
    {
        $tenant = $this->tenants->update($tenant, ['is_active' => true]);

        activity('audit')->performedOn($tenant)->tap(fn ($a) => $a->tenant_id = $tenant->id)->log("Tenant \"{$tenant->name}\" activated");

        return $tenant;
    }

    public function subscriptionFor(Tenant $tenant): Subscription
    {
        return $tenant->activeSubscription()->with('plan')->firstOrFail();
    }

    public function changePlan(Tenant $tenant, Plan $plan, User $actor): Subscription
    {
        return $this->subscriptions->changePlan($this->subscriptionFor($tenant), $plan, $actor);
    }

    public function renewSubscription(Tenant $tenant, ?BillingCycle $cycle, User $actor): Subscription
    {
        return $this->subscriptions->renew($this->subscriptionFor($tenant), $cycle, $actor);
    }

    public function cancelSubscription(Tenant $tenant, User $actor): Subscription
    {
        return $this->subscriptions->cancel($this->subscriptionFor($tenant), $actor);
    }

    public function resumeSubscription(Tenant $tenant, User $actor): Subscription
    {
        return $this->subscriptions->resume($this->subscriptionFor($tenant), $actor);
    }

    public function suspendSubscription(Tenant $tenant, User $actor): Subscription
    {
        return $this->subscriptions->suspend($this->subscriptionFor($tenant), $actor);
    }

    public function reactivateSubscription(Tenant $tenant, User $actor): Subscription
    {
        return $this->subscriptions->reactivate($this->subscriptionFor($tenant), $actor);
    }

    public function subscriptionPayments(Tenant $tenant): Collection
    {
        return SubscriptionPayment::query()
            ->where('tenant_id', $tenant->id)
            ->with(['plan', 'recordedBy'])
            ->latest('paid_at')
            ->get();
    }
}
