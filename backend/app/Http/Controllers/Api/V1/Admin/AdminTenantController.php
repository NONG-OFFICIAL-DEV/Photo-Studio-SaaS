<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\DeleteTenantAction;
use App\Enums\BillingCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangePlanRequest;
use App\Http\Requests\Admin\DeleteTenantRequest;
use App\Http\Requests\Admin\RenewSubscriptionRequest;
use App\Http\Resources\AdminTenantResource;
use App\Http\Resources\SubscriptionPaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\AdminTenantService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTenantController extends Controller
{
    use ApiResponse;

    public function __construct(protected AdminTenantService $tenants)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->tenants->paginate($request->only(['search', 'status', 'sortBy', 'sortDesc', 'perPage']));

        return $this->success(
            AdminTenantResource::collection($paginator->items()),
            meta: [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(string $tenant): JsonResponse
    {
        return $this->success(new AdminTenantResource($this->tenants->show($tenant)));
    }

    public function suspend(Tenant $tenant): JsonResponse
    {
        return $this->success(new AdminTenantResource($this->tenants->suspend($tenant)), 'Tenant suspended successfully.');
    }

    public function activate(Tenant $tenant): JsonResponse
    {
        return $this->success(new AdminTenantResource($this->tenants->activate($tenant)), 'Tenant activated successfully.');
    }

    public function changePlan(ChangePlanRequest $request, Tenant $tenant): JsonResponse
    {
        $plan = Plan::findOrFail($request->validated('plan_id'));
        $subscription = $this->tenants->changePlan($tenant, $plan, $request->user());

        return $this->success(new SubscriptionResource($subscription), 'Plan changed successfully.');
    }

    public function renewSubscription(RenewSubscriptionRequest $request, Tenant $tenant): JsonResponse
    {
        $cycle = $request->validated('billing_cycle') ? BillingCycle::from($request->validated('billing_cycle')) : null;
        $subscription = $this->tenants->renewSubscription($tenant, $cycle, $request->user());

        return $this->success(new SubscriptionResource($subscription), 'Subscription renewed successfully.');
    }

    public function cancelSubscription(Request $request, Tenant $tenant): JsonResponse
    {
        $subscription = $this->tenants->cancelSubscription($tenant, $request->user());

        return $this->success(new SubscriptionResource($subscription), 'Subscription will end at the close of the current period.');
    }

    public function resumeSubscription(Request $request, Tenant $tenant): JsonResponse
    {
        $subscription = $this->tenants->resumeSubscription($tenant, $request->user());

        return $this->success(new SubscriptionResource($subscription), 'Cancellation reversed successfully.');
    }

    public function suspendSubscription(Request $request, Tenant $tenant): JsonResponse
    {
        $subscription = $this->tenants->suspendSubscription($tenant, $request->user());

        return $this->success(new SubscriptionResource($subscription), 'Subscription suspended successfully.');
    }

    public function reactivateSubscription(Request $request, Tenant $tenant): JsonResponse
    {
        $subscription = $this->tenants->reactivateSubscription($tenant, $request->user());

        return $this->success(new SubscriptionResource($subscription), 'Subscription reactivated successfully.');
    }

    public function subscriptionPayments(Tenant $tenant): JsonResponse
    {
        return $this->success(SubscriptionPaymentResource::collection($this->tenants->subscriptionPayments($tenant)));
    }

    public function destroy(DeleteTenantRequest $request, Tenant $tenant, DeleteTenantAction $action): JsonResponse
    {
        $summary = $action->execute($tenant, $request->user());

        return $this->noContent("Tenant \"{$summary['tenant_name']}\" and all of its data have been permanently deleted.");
    }
}
