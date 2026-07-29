<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Services\PlanService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    use ApiResponse;

    public function __construct(protected PlanService $plans)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->plans->paginate($request->only(['search', 'sortBy', 'sortDesc', 'perPage']));

        return $this->success(
            PlanResource::collection($paginator->items()),
            meta: [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->plans->create($request->validated());

        return $this->created(new PlanResource($plan), 'Plan created successfully.');
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan = $this->plans->update($plan, $request->validated());

        return $this->success(new PlanResource($plan), 'Plan updated successfully.');
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->plans->delete($plan);

        return $this->noContent('Plan deleted successfully.');
    }
}
