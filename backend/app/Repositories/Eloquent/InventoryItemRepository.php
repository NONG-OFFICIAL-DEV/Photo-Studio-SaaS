<?php

namespace App\Repositories\Eloquent;

use App\Models\InventoryItem;
use App\Repositories\Contracts\InventoryItemRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class InventoryItemRepository extends BaseRepository implements InventoryItemRepositoryInterface
{
    protected array $searchable = ['name', 'sku'];

    public function __construct(InventoryItem $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (array_key_exists('low_stock', $filters) && filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull('reorder_threshold')
                ->whereColumn('quantity_on_hand', '<=', 'reorder_threshold');
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('name');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }
}
