<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanFeatureListingRequest;
use App\Http\Requests\Admin\UpdatePlanFeatureListingRequest;
use App\Http\Resources\PlanFeatureListingResource;
use App\Models\PlanFeatureListing;
use App\Services\PlanFeatureListingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminPlanFeatureListingController extends Controller
{
    use ApiResponse;

    public function __construct(protected PlanFeatureListingService $listings) {}

    public function index(): JsonResponse
    {
        return $this->success(PlanFeatureListingResource::collection($this->listings->all()));
    }

    public function store(StorePlanFeatureListingRequest $request): JsonResponse
    {
        $listing = $this->listings->create($request->validated());

        return $this->created(new PlanFeatureListingResource($listing), 'Feature created successfully.');
    }

    public function update(UpdatePlanFeatureListingRequest $request, PlanFeatureListing $planFeatureListing): JsonResponse
    {
        $listing = $this->listings->update($planFeatureListing, $request->validated());

        return $this->success(new PlanFeatureListingResource($listing), 'Feature updated successfully.');
    }

    public function destroy(PlanFeatureListing $planFeatureListing): JsonResponse
    {
        $this->listings->delete($planFeatureListing);

        return $this->noContent('Feature deleted successfully.');
    }
}
