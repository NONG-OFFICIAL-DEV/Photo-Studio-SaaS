<?php

namespace App\Http\Controllers\Api\V1\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceAddOnRequest;
use App\Http\Requests\Service\UpdateServiceAddOnRequest;
use App\Http\Resources\ServiceAddOnResource;
use App\Models\ServiceAddOn;
use App\Services\ServiceAddOnService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceAddOnController extends Controller
{
    use ApiResponse;

    public function __construct(protected ServiceAddOnService $addOns)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('services.view'), 403);

        return $this->success(ServiceAddOnResource::collection($this->addOns->all()));
    }

    public function store(StoreServiceAddOnRequest $request): JsonResponse
    {
        $addOn = $this->addOns->create($request->validated());

        return $this->created(new ServiceAddOnResource($addOn), 'Add-on created successfully.');
    }

    public function update(UpdateServiceAddOnRequest $request, ServiceAddOn $addon): JsonResponse
    {
        $addon = $this->addOns->update($addon, $request->validated());

        return $this->success(new ServiceAddOnResource($addon), 'Add-on updated successfully.');
    }

    public function destroy(Request $request, ServiceAddOn $addon): JsonResponse
    {
        abort_unless($request->user()->can('services.delete'), 403);

        $this->addOns->delete($addon);

        return $this->noContent('Add-on deleted successfully.');
    }
}
