<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    protected array $searchable = ['name', 'slug', 'email', 'domain'];

    public function __construct(Tenant $model)
    {
        parent::__construct($model);
    }

    public function slugExists(string $slug): bool
    {
        return $this->model->newQuery()->where('slug', $slug)->exists();
    }

    /**
     * Platform admin's tenant list — eager-loads what the admin panel needs
     * (owner user, active subscription + plan, user count) that the
     * tenant-scoped TenantResource has no reason to load.
     */
    public function adminPaginate(array $filters): LengthAwarePaginator
    {
        $query = $this->query()
            ->withCount('users')
            ->with(['activeSubscription.plan']);

        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sortBy'] ?? null, $filters['sortDesc'] ?? false);

        $perPage = (int) ($filters['perPage'] ?? 15);

        return $query->paginate($perPage > 0 ? $perPage : 15)->withQueryString();
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }
    }
}
