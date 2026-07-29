<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminTenantResource;
use App\Models\Tenant;
use App\Services\AdminTenantService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTenantController extends Controller
{
    use ApiResponse;

    public function __construct(protected AdminTenantService $tenants)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->tenants->paginate($request->only(['search', 'status', 'sortBy', 'sortDesc', 'perPage']));

        return $this->success(
            AdminTenantResource::collection($paginator->items()),
            meta: [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(string $tenant): JsonResponse
    {
        return $this->success(new AdminTenantResource($this->tenants->show($tenant)));
    }

    public function suspend(Tenant $tenant): JsonResponse
    {
        return $this->success(new AdminTenantResource($this->tenants->suspend($tenant)), 'Tenant suspended successfully.');
    }

    public function activate(Tenant $tenant): JsonResponse
    {
        return $this->success(new AdminTenantResource($this->tenants->activate($tenant)), 'Tenant activated successfully.');
    }
}
