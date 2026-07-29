<?php

namespace App\Services;

use App\Models\Service;
use App\Models\User;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Business logic for the Service (package/pricing) model. Named
 * "Catalog" rather than "ServiceService" to avoid colliding with the
 * Eloquent model's own name.
 */
class ServiceCatalogService extends BaseService
{
    public function __construct(protected ServiceRepositoryInterface $services)
    {
        parent::__construct($services);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->services->paginateServer($filters);
    }

    public function create(array $data, ?User $creator = null): Service
    {
        /** @var Service $service */
        $service = $this->services->create([...$data, 'created_by' => $creator?->id]);

        return $service->load('category');
    }

    public function update(Service $service, array $data): Service
    {
        $this->services->update($service, $data);

        return $service->fresh('category');
    }

    public function delete(Service $service): bool
    {
        return $this->services->delete($service);
    }
}
