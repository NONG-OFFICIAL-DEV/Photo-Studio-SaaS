<?php

namespace App\Repositories\Eloquent;

use App\Models\Package;
use App\Repositories\Contracts\PackageRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class PackageRepository extends BaseRepository implements PackageRepositoryInterface
{
    protected array $searchable = ['name', 'description'];

    public function __construct(Package $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['components.service', 'components.addon']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('sort_order')->orderBy('name');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }
}
