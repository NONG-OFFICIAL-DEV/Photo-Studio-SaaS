<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\BranchService;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use ApiResponse;

    public function __construct(protected BranchService $branches, protected SubscriptionService $subscriptions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('branches.view'), 403);

        return $this->success(BranchResource::collection($this->branches->all($request->boolean('include_inactive'))));
    }

    /**
     * Blocked once the plan's max_branches limit is reached (see
     * SubscriptionService::assertCanAddBranch()).
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $this->subscriptions->assertCanAddBranch($request->user()->tenant);

        $branch = $this->branches->create($request->validated());

        return $this->created(new BranchResource($branch), 'Branch created successfully.');
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $branch = $this->branches->update($branch, $request->validated());

        return $this->success(new BranchResource($branch), 'Branch updated successfully.');
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->can('branches.delete'), 403);

        $this->branches->delete($branch);

        return $this->noContent('Branch deleted successfully.');
    }
}
