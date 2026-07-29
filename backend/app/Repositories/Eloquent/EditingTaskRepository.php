<?php

namespace App\Repositories\Eloquent;

use App\Models\EditingTask;
use App\Repositories\Contracts\EditingTaskRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class EditingTaskRepository extends BaseRepository implements EditingTaskRepositoryInterface
{
    public function __construct(EditingTask $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['order.customer', 'assignedUser']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['assigned_user_id'])) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
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
