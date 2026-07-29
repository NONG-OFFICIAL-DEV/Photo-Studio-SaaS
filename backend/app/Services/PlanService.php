<?php

namespace App\Services;

use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlanService extends BaseService
{
    public function __construct(protected PlanRepositoryInterface $plans)
    {
        parent::__construct($plans);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->plans->paginateServer($filters);
    }

    public function create(array $data): Plan
    {
        return $this->plans->create($data);
    }

    public function update(Plan $plan, array $data): Plan
    {
        return $this->plans->update($plan, $data);
    }

    public function delete(Plan $plan): bool
    {
        if ($plan->subscriptions()->exists()) {
            throw new HttpException(422, 'This plan has active tenant subscriptions and cannot be deleted — deactivate it instead.');
        }

        return $this->plans->delete($plan);
    }
}
