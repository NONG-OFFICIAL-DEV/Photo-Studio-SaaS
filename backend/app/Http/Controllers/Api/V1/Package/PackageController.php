<?php

namespace App\Http\Controllers\Api\V1\Package;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\StorePackageRequest;
use App\Http\Requests\Package\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Services\PackageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    use ApiResponse;

    public function __construct(protected PackageService $packages)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Package::class);

        $paginator = $this->packages->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage', 'is_active',
        ]));

        return $this->success(
            PackageResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = $this->packages->create($request->validated(), $request->user());

        return $this->created(new PackageResource($package), 'Package created successfully.');
    }

    public function show(Package $package): JsonResponse
    {
        $this->authorize('view', $package);

        return $this->success(new PackageResource($package->load('components.service', 'components.addon')));
    }

    public function update(UpdatePackageRequest $request, Package $package): JsonResponse
    {
        $package = $this->packages->update($package, $request->validated());

        return $this->success(new PackageResource($package), 'Package updated successfully.');
    }

    public function destroy(Package $package): JsonResponse
    {
        $this->authorize('delete', $package);

        $this->packages->delete($package);

        return $this->noContent('Package deleted successfully.');
    }
}
