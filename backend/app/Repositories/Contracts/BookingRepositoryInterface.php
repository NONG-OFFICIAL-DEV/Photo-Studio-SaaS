<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface BookingRepositoryInterface extends RepositoryInterface
{
    public function hasConflict(string $userId, Carbon $startsAt, Carbon $endsAt, ?string $excludeBookingId = null): bool;

    public function inRange(Carbon $start, Carbon $end, array $filters = []): Collection;
}
