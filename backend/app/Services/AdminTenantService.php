<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminTenantService extends BaseService
{
    public function __construct(protected TenantRepositoryInterface $tenants)
    {
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
}
