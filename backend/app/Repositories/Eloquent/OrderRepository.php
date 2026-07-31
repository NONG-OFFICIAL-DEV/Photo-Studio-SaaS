<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['customer', 'editingTask', 'items']);
    }

    protected function applySearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $query->whereHas('customer', fn (Builder $c) => $c->where('name', 'ilike', "%{$term}%"));
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['booking_id'])) {
            $query->where('booking_id', $filters['booking_id']);
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
