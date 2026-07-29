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
        return $this->tenants->update($tenant, ['is_active' => false]);
    }

    public function activate(Tenant $tenant): Tenant
    {
        return $this->tenants->update($tenant, ['is_active' => true]);
    }
}
