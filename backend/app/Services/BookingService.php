<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class BookingService extends BaseService
{
    public function __construct(protected BookingRepositoryInterface $bookings, protected BranchResolutionService $branches)
    {
        parent::__construct($bookings);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->bookings->paginateServer($filters);
    }

    public function calendarRange(Carbon $start, Carbon $end, array $filters = []): Collection
    {
        return $this->bookings->inRange($start, $end, $filters);
    }

    public function create(array $data, ?User $creator = null): Booking
    {
        $branchId = $this->branches->resolveForCreate($creator->tenant, $data['branch_id'] ?? null);

        /** @var Booking $booking */
        $booking = $this->bookings->create([...$data, 'branch_id' => $branchId, 'created_by' => $creator?->id]);

        return $booking->load('customer', 'assignedUser');
    }

    public function update(Booking $booking, array $data): Booking
    {
        $this->bookings->update($booking, $data);

        return $booking->fresh(['customer', 'assignedUser']);
    }

    public function delete(Booking $booking): bool
    {
        return $this->bookings->delete($booking);
    }

    public function confirm(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::Confirmed]);

        return $booking;
    }

    public function start(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::InProgress]);

        return $booking;
    }

    public function complete(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::Completed]);

        return $booking;
    }

    public function cancel(Booking $booking, string $reason): Booking
    {
        $booking->update(['status' => BookingStatus::Cancelled, 'cancelled_reason' => $reason]);

        return $booking;
    }

    public function markNoShow(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::NoShow]);

        return $booking;
    }
}
