<?php

namespace App\Repositories\Eloquent;

use App\Models\Expense;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ExpenseRepository extends BaseRepository implements ExpenseRepositoryInterface
{
    protected array $searchable = ['vendor', 'notes'];

    public function __construct(Expense $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with('category');
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('expense_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('expense_date', '<=', $filters['date_to']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('expense_date', 'desc');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }
}
