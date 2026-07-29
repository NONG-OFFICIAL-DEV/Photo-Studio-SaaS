<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ApiLogResource;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform-wide counterpart to Api\V1\Audit\AuditController — same
 * AuditService, but with an optional ?tenant_id= filter instead of always
 * scoping to the caller's own tenant (a super admin has none). Omitting
 * tenant_id returns every tenant's entries, for cross-tenant investigation.
 */
class AdminAuditController extends Controller
{
    use ApiResponse;

    protected const FILTER_KEYS = ['search', 'date_from', 'date_to', 'perPage', 'tenant_id'];

    public function __construct(protected AuditService $audit)
    {
    }

    public function activityLog(Request $request): JsonResponse
    {
        return $this->paginated(ActivityLogResource::class, $this->audit->activityLog($this->tenantId($request), $this->filters($request)));
    }

    public function auditLog(Request $request): JsonResponse
    {
        return $this->paginated(ActivityLogResource::class, $this->audit->auditLog($this->tenantId($request), $this->filters($request)));
    }

    public function loginHistory(Request $request): JsonResponse
    {
        return $this->paginated(ActivityLogResource::class, $this->audit->loginHistory($this->tenantId($request), $this->filters($request)));
    }

    public function securityEvents(Request $request): JsonResponse
    {
        return $this->paginated(ActivityLogResource::class, $this->audit->securityEvents($this->tenantId($request), $this->filters($request)));
    }

    public function apiLogs(Request $request): JsonResponse
    {
        return $this->paginated(ApiLogResource::class, $this->audit->apiLogs($this->tenantId($request), $this->filters($request, ['method'])));
    }

    protected function tenantId(Request $request): ?string
    {
        return $request->query('tenant_id') ?: null;
    }

    protected function filters(Request $request, array $extra = []): array
    {
        return $request->only([...self::FILTER_KEYS, ...$extra]);
    }

    protected function paginated(string $resourceClass, LengthAwarePaginator $paginator): JsonResponse
    {
        return $this->success(
            $resourceClass::collection($paginator->items()),
            meta: [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
