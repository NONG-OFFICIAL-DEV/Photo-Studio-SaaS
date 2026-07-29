<?php

namespace App\Repositories\Eloquent;

use App\Models\AttendanceRecord;
use App\Repositories\Contracts\AttendanceRecordRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class AttendanceRecordRepository extends BaseRepository implements AttendanceRecordRepositoryInterface
{
    public function __construct(AttendanceRecord $model)
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

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('date', 'desc');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }
}
