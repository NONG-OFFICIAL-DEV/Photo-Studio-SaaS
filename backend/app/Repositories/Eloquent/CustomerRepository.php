<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    protected array $searchable = ['name', 'email', 'phone'];

    public function __construct(Customer $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with('tags');
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn (Builder $q) => $q->where('customer_tags.id', $filters['tag_id']));
        }

        if (array_key_exists('is_favorite', $filters) && $filters['is_favorite'] !== null) {
            $query->where('is_favorite', filter_var($filters['is_favorite'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_blacklisted', $filters) && $filters['is_blacklisted'] !== null) {
            $query->where('is_blacklisted', filter_var($filters['is_blacklisted'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
    }
}
