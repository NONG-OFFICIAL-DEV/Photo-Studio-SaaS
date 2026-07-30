<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        $plan = $this->plans->create($data);

        activity('audit')->performedOn($plan)->log("Plan \"{$plan->name}\" created");

        return $plan;
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan = $this->plans->update($plan, $data);

        activity('audit')->performedOn($plan)->withProperties(['changed' => array_keys($data)])->log("Plan \"{$plan->name}\" updated");

        return $plan;
    }

    public function delete(Plan $plan): bool
    {
        if ($plan->subscriptions()->exists()) {
            throw new ApiException(422, 'This plan has active tenant subscriptions and cannot be deleted — deactivate it instead.', 'PLAN_HAS_ACTIVE_SUBSCRIPTIONS');
        }

        $name = $plan->name;
        $result = $this->plans->delete($plan);

        activity('audit')->log("Plan \"{$name}\" deleted");

        return $result;
    }
}
