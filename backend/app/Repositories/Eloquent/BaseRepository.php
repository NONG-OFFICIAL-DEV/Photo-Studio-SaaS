<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Eloquent repository shared by every concrete repository.
 *
 * Server-driven tables (v-data-table-server on the frontend) all speak the
 * same filter contract via paginateServer(): search / sortBy / sortDesc /
 * page / perPage, plus arbitrary column filters handled by applyFilters().
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected Model $model;

    /**
     * Columns eligible for the free-text "search" filter. Override per repo.
     *
     * @var string[]
     */
    protected array $searchable = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->query()->get($columns);
    }

    public function find(string $id, array $columns = ['*']): ?Model
    {
        return $this->query()->find($id, $columns);
    }

    public function findOrFail(string $id, array $columns = ['*']): Model
    {
        return $this->query()->findOrFail($id, $columns);
    }

    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function paginateServer(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query();

        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sortBy'] ?? null, $filters['sortDesc'] ?? false);

        $perPage = (int) ($filters['perPage'] ?? $perPage);

        return $query->paginate($perPage > 0 ? $perPage : 15)->withQueryString();
    }

    protected function applySearch(Builder $query, ?string $term): void
    {
        if (! $term || empty($this->searchable)) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            foreach ($this->searchable as $column) {
                $q->orWhere($column, 'ilike', "%{$term}%");
            }
        });
    }

    /**
     * Hook for concrete repositories to apply extra column filters.
     * Override to handle repo-specific filter keys.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        //
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->latest();

            return;
        }

        $direction = filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc';
        $query->orderBy($sortBy, $direction);
    }
}
