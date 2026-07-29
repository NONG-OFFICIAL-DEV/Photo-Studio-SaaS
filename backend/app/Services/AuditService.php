<?php

namespace App\Services;

use App\Models\ApiLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Backs all 5 tabs of the Audit page. Reads from two sources:
 *  - the shared `activity_log` table (Spatie) — every tenant model already
 *    writes here under its own log_name (customer/order/invoice/...), and
 *    this service additionally reads the 'audit' (sensitive admin actions,
 *    see TenantSettingsService/PlanService/AdminTenantService) and 'login'/
 *    'security' log names (see SecurityEventLogger).
 *  - the dedicated `api_logs` table (high-volume, different shape).
 *
 * Every method accepts $tenantId = null to mean "no filter" — the Super
 * Admin cross-tenant view (AdminAuditController) calls with null or an
 * explicit tenant id; the tenant-scoped view (AuditController) always
 * passes the current user's own tenant_id.
 */
class AuditService
{
    /**
     * log_names that have a dedicated tab and must NOT also appear in the
     * generic Activity Log tab (which shows business-data CRUD only).
     */
    protected const RESERVED_LOG_NAMES = ['audit', 'login', 'security'];

    public function activityLog(?string $tenantId, array $filters): LengthAwarePaginator
    {
        return $this->paginateActivity(
            Activity::query()->whereNotIn('log_name', self::RESERVED_LOG_NAMES),
            $tenantId,
            $filters,
        );
    }

    public function auditLog(?string $tenantId, array $filters): LengthAwarePaginator
    {
        return $this->paginateActivity(
            Activity::query()->where('log_name', 'audit'),
            $tenantId,
            $filters,
        );
    }

    public function loginHistory(?string $tenantId, array $filters): LengthAwarePaginator
    {
        return $this->paginateActivity(
            Activity::query()->where('log_name', 'login'),
            $tenantId,
            $filters,
        );
    }

    /**
     * Permission-denied events (log_name='security') plus failed login
     * attempts (log_name='login' with properties.success = false) —
     * merged here at read time rather than double-written.
     */
    public function securityEvents(?string $tenantId, array $filters): LengthAwarePaginator
    {
        $query = Activity::query()->where(function (Builder $q) {
            $q->where('log_name', 'security')
                ->orWhere(function (Builder $q2) {
                    $q2->where('log_name', 'login')->where('properties->success', false);
                });
        });

        return $this->paginateActivity($query, $tenantId, $filters);
    }

    public function apiLogs(?string $tenantId, array $filters): LengthAwarePaginator
    {
        $query = ApiLog::query()->with('user');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (! empty($filters['search'])) {
            $query->where('path', 'ilike', '%'.$filters['search'].'%');
        }

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        $this->applyDateRange($query, $filters, 'created_at');

        $perPage = (int) ($filters['perPage'] ?? 15);

        return $query->latest('created_at')->paginate($perPage > 0 ? $perPage : 15)->withQueryString();
    }

    protected function paginateActivity(Builder $query, ?string $tenantId, array $filters): LengthAwarePaginator
    {
        $query->with('causer');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (! empty($filters['search'])) {
            $query->where('description', 'ilike', '%'.$filters['search'].'%');
        }

        $this->applyDateRange($query, $filters, 'created_at');

        $perPage = (int) ($filters['perPage'] ?? 15);

        return $query->latest('created_at')->paginate($perPage > 0 ? $perPage : 15)->withQueryString();
    }

    protected function applyDateRange(Builder $query, array $filters, string $column): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }
}
