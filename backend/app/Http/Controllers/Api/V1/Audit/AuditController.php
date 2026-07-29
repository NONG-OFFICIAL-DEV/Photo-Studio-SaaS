<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ApiLogResource;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    use ApiResponse;

    protected const FILTER_KEYS = ['search', 'date_from', 'date_to', 'perPage'];

    public function __construct(protected AuditService $audit)
    {
    }

    public function activityLog(Request $request): JsonResponse
    {
        $this->authorizeAudit($request);

        return $this->paginated(ActivityLogResource::class, $this->audit->activityLog($this->tenantId($request), $this->filters($request)));
    }

    public function auditLog(Request $request): JsonResponse
    {
        $this->authorizeAudit($request);

        return $this->paginated(ActivityLogResource::class, $this->audit->auditLog($this->tenantId($request), $this->filters($request)));
    }

    public function loginHistory(Request $request): JsonResponse
    {
        $this->authorizeAudit($request);

        return $this->paginated(ActivityLogResource::class, $this->audit->loginHistory($this->tenantId($request), $this->filters($request)));
    }

    public function securityEvents(Request $request): JsonResponse
    {
        $this->authorizeAudit($request);

        return $this->paginated(ActivityLogResource::class, $this->audit->securityEvents($this->tenantId($request), $this->filters($request)));
    }

    public function apiLogs(Request $request): JsonResponse
    {
        $this->authorizeAudit($request);

        return $this->paginated(ApiLogResource::class, $this->audit->apiLogs($this->tenantId($request), $this->filters($request, ['method'])));
    }

    protected function authorizeAudit(Request $request): void
    {
        abort_unless($request->user()->can('audit.view'), 403);
    }

    protected function tenantId(Request $request): ?string
    {
        return $request->user()->tenant_id;
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
