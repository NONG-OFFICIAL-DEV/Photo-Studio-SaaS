<?php

namespace App\Repositories\Eloquent;

use App\Models\ExpenseCategory;
use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ExpenseCategoryRepository extends BaseRepository implements ExpenseCategoryRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(ExpenseCategory $model)
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
