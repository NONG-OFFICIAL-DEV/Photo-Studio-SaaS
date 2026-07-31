<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class BookingRepository extends BaseRepository implements BookingRepositoryInterface
{
    public function __construct(Booking $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['customer', 'assignedUser']);
    }

    protected function applySearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'ilike', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'ilike', "%{$term}%"));
        });
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['assigned_user_id'])) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! $sortBy) {
            $query->orderBy('starts_at', 'desc');

            return;
        }

        parent::applySort($query, $sortBy, $sortDesc);
    }

    /**
     * True if the given user already has a non-cancelled booking whose time
     * range overlaps [$startsAt, $endsAt). Standard interval-overlap check:
     * two ranges overlap unless one ends before the other starts.
     */
    public function hasConflict(string $userId, Carbon $startsAt, Carbon $endsAt, ?string $excludeBookingId = null): bool
    {
        return $this->model->newQuery()
            ->where('assigned_user_id', $userId)
            ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::NoShow->value])
            ->when($excludeBookingId, fn (Builder $q) => $q->where('id', '!=', $excludeBookingId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    /**
     * $end is treated as inclusive of that whole calendar day (callers pass
     * "the last day I want to see", e.g. a month or week grid's last day —
     * not a precise instant) — comparing against midnight of the day AFTER
     * $end, rather than $end itself, so a booking on $end's own date isn't
     * silently dropped for starting after midnight of that same day.
     */
    public function inRange(Carbon $start, Carbon $end, array $filters = []): Collection
    {
        $query = $this->query()
            ->where('starts_at', '<', $end->copy()->addDay()->startOfDay())
            ->where('ends_at', '>', $start);

        $this->applyFilters($query, $filters);

        return $query->orderBy('starts_at')->get();
    }
}
