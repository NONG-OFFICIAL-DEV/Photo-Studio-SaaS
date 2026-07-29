<?php

namespace App\Http\Controllers\Api\V1\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ServiceCatalogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use ApiResponse;

    public function __construct(protected ServiceCatalogService $services)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $paginator = $this->services->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage',
            'category_id', 'is_active', 'pricing_unit',
        ]));

        return $this->success(
            ServiceResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->services->create($request->validated(), $request->user());

        return $this->created(new ServiceResource($service), 'Service created successfully.');
    }

    public function show(Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return $this->success(new ServiceResource($service->load('category')));
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service = $this->services->update($service, $request->validated());

        return $this->success(new ServiceResource($service), 'Service updated successfully.');
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $this->services->delete($service);

        return $this->noContent('Service deleted successfully.');
    }
}
