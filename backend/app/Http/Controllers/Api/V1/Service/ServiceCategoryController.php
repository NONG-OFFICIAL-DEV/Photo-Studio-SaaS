<?php

namespace App\Http\Controllers\Api\V1\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceCategoryRequest;
use App\Http\Requests\Service\UpdateServiceCategoryRequest;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Services\ServiceCategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(protected ServiceCategoryService $categories)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('services.view'), 403);

        return $this->success(ServiceCategoryResource::collection($this->categories->all()));
    }

    public function store(StoreServiceCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->validated());

        return $this->created(new ServiceCategoryResource($category), 'Category created successfully.');
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $category): JsonResponse
    {
        $category = $this->categories->update($category, $request->validated());

        return $this->success(new ServiceCategoryResource($category), 'Category updated successfully.');
    }

    public function destroy(Request $request, ServiceCategory $category): JsonResponse
    {
        abort_unless($request->user()->can('services.delete'), 403);

        $this->categories->delete($category);

        return $this->noContent('Category deleted successfully.');
    }
}
