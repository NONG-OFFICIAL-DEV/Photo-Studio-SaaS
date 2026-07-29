<?php

namespace App\Repositories\Eloquent;

use App\Models\ServiceCategory;
use App\Repositories\Contracts\ServiceCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ServiceCategoryRepository extends BaseRepository implements ServiceCategoryRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(ServiceCategory $model)
    {
        parent::__construct($model);
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
