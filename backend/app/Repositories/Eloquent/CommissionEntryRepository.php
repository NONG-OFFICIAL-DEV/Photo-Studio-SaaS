<?php

namespace App\Repositories\Eloquent;

use App\Models\CommissionEntry;
use App\Repositories\Contracts\CommissionEntryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CommissionEntryRepository extends BaseRepository implements CommissionEntryRepositoryInterface
{
    public function __construct(CommissionEntry $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['user', 'order']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('earned_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('earned_date', '<=', $filters['date_to']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('earned_date', 'desc');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }
}
