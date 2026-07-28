<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerTagRequest;
use App\Http\Requests\Customer\UpdateCustomerTagRequest;
use App\Http\Resources\CustomerTagResource;
use App\Models\CustomerTag;
use App\Services\CustomerTagService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTagController extends Controller
{
    use ApiResponse;

    public function __construct(protected CustomerTagService $tags)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('customers.view'), 403);

        return $this->success(CustomerTagResource::collection($this->tags->all()));
    }

    public function store(StoreCustomerTagRequest $request): JsonResponse
    {
        $tag = $this->tags->create($request->validated());

        return $this->created(new CustomerTagResource($tag), 'Tag created successfully.');
    }

    public function update(UpdateCustomerTagRequest $request, CustomerTag $tag): JsonResponse
    {
        $tag = $this->tags->update($tag, $request->validated());

        return $this->success(new CustomerTagResource($tag), 'Tag updated successfully.');
    }

    public function destroy(Request $request, CustomerTag $tag): JsonResponse
    {
        abort_unless($request->user()->can('customers.delete'), 403);

        $this->tags->delete($tag);

        return $this->noContent('Tag deleted successfully.');
    }
}
