<?php

namespace App\Repositories\Eloquent;

use App\Models\PlanFeatureListing;
use App\Repositories\Contracts\PlanFeatureListingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class PlanFeatureListingRepository extends BaseRepository implements PlanFeatureListingRepositoryInterface
{
    protected array $searchable = ['key', 'label_en', 'label_km'];

    public function __construct(PlanFeatureListing $model)
    {
        parent::__construct($model);
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('sort_order')->orderBy('label_en');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }
}
