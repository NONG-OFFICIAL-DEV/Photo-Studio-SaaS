<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->can('bookings.view') && $user->tenant_id === $booking->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('bookings.create');
    }

    /**
     * Full schedulers (bookings.assign — Owner/Manager/Receptionist) can
     * update any booking. Everyone else with bookings.update (Photographer)
     * can only update a booking assigned to them — e.g. marking their own
     * shoot complete, not rescheduling someone else's.
     */
    public function update(User $user, Booking $booking): bool
    {
        if ($user->tenant_id !== $booking->tenant_id) {
            return false;
        }

        if ($user->can('bookings.assign')) {
            return $user->can('bookings.update');
        }

        return $user->can('bookings.update') && $booking->assigned_user_id === $user->id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->can('bookings.delete') && $user->tenant_id === $booking->tenant_id;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->can('bookings.cancel') && $user->tenant_id === $booking->tenant_id;
    }
}
