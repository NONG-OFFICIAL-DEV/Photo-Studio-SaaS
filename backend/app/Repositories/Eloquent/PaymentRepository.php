<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    public function __construct(Payment $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['recordedBy']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('paid_at', 'desc');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }
}
