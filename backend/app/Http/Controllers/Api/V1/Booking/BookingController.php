<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    use ApiResponse;

    public function __construct(protected BookingService $bookings)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $paginator = $this->bookings->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage',
            'status', 'type', 'assigned_user_id', 'customer_id',
        ]));

        return $this->success(
            BookingResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $bookings = $this->bookings->calendarRange(
            Carbon::parse($request->query('start')),
            Carbon::parse($request->query('end')),
            $request->only(['status', 'type', 'assigned_user_id']),
        );

        return $this->success(BookingResource::collection($bookings));
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->create($request->validated(), $request->user());

        return $this->created(new BookingResource($booking), 'Booking created successfully.');
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $booking->load('customer', 'assignedUser', 'createdBy');

        return $this->success(new BookingResource($booking));
    }

    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookings->update($booking, $request->validated());

        return $this->success(new BookingResource($booking), 'Booking updated successfully.');
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $this->authorize('delete', $booking);

        $this->bookings->delete($booking);

        return $this->noContent('Booking deleted successfully.');
    }

    public function confirm(Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        return $this->success(new BookingResource($this->bookings->confirm($booking)), 'Booking confirmed.');
    }

    public function start(Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        return $this->success(new BookingResource($this->bookings->start($booking)), 'Booking marked in progress.');
    }

    public function complete(Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        return $this->success(new BookingResource($this->bookings->complete($booking)), 'Booking marked complete.');
    }

    public function noShow(Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        return $this->success(new BookingResource($this->bookings->markNoShow($booking)), 'Booking marked as no-show.');
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookings->cancel($booking, $request->string('reason')->toString());

        return $this->success(new BookingResource($booking), 'Booking cancelled.');
    }
}
