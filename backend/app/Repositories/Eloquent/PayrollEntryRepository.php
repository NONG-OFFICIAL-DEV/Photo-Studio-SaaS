<?php

namespace App\Repositories\Eloquent;

use App\Models\PayrollEntry;
use App\Repositories\Contracts\PayrollEntryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class PayrollEntryRepository extends BaseRepository implements PayrollEntryRepositoryInterface
{
    public function __construct(PayrollEntry $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with('user');
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('period_start', 'desc');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }
}
