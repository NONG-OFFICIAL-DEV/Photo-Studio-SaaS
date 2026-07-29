<?php

namespace App\Repositories\Eloquent;

use App\Models\Album;
use App\Repositories\Contracts\AlbumRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class AlbumRepository extends BaseRepository implements AlbumRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(Album $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['customer', 'order']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('created_at', 'desc');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }
}
