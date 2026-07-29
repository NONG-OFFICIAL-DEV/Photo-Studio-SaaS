<?php

namespace App\Repositories\Eloquent;

use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class PlanRepository extends BaseRepository implements PlanRepositoryInterface
{
    protected array $searchable = ['name', 'code'];

    public function __construct(Plan $model)
    {
        parent::__construct($model);
    }

    public function codeExists(string $code): bool
    {
        return $this->model->newQuery()->where('code', $code)->exists();
    }

    public function query(): Builder
    {
        return parent::query()->withCount('subscriptions');
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
