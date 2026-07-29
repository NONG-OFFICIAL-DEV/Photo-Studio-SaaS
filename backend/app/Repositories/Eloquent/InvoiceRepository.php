<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class InvoiceRepository extends BaseRepository implements InvoiceRepositoryInterface
{
    protected array $searchable = ['invoice_number'];

    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['customer', 'order']);
    }

    protected function applySearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('invoice_number', 'ilike', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'ilike', "%{$term}%"));
        });
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
