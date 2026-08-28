<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\EditingStatus;
use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Package;
use App\Models\Service;
use App\Models\ServiceAddOn;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService extends BaseService
{
    public function __construct(protected OrderRepositoryInterface $orders, protected BranchResolutionService $branches)
    {
        parent::__construct($orders);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->orders->paginateServer($filters);
    }

    public function create(array $data, ?User $creator = null): Order
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        $booking = null;
        if (! empty($data['booking_id'])) {
            $booking = Booking::query()->findOrFail($data['booking_id']);
            $this->assertBookingIsConfirmable($booking);
        }

        return DB::transaction(function () use ($data, $items, $creator, $booking) {
            $lines = $this->resolveLines($items);

            // A booking-linked order always inherits its branch from the
            // booking — never re-asked. Only a standalone order (no
            // booking) falls back to the usual create-time resolution.
            $branchId = $booking
                ? $booking->branch_id
                : $this->branches->resolveForCreate($creator->tenant, $data['branch_id'] ?? null);

            /** @var Order $order */
            $order = $this->orders->create([
                ...$data,
                'branch_id' => $branchId,
                'subtotal' => $lines['subtotal'],
                'total' => max(0, $lines['subtotal'] - ($data['discount_amount'] ?? 0)),
                'created_by' => $creator?->id,
            ]);

            $order->items()->createMany($lines['items']);

            return $order->load('items', 'customer');
        });
    }

    public function update(Order $order, array $data): Order
    {
        $items = $data['items'] ?? null;
        unset($data['items']);

        return DB::transaction(function () use ($order, $data, $items) {
            $subtotal = (float) $order->subtotal;

            if ($items !== null) {
                if ($order->status !== OrderStatus::Pending && $order->status !== OrderStatus::Confirmed) {
                    throw new ApiException(422, 'Line items can no longer be changed once an order has entered production.', 'LINE_ITEMS_LOCKED_IN_PRODUCTION');
                }

                $lines = $this->resolveLines($items);
                $subtotal = $lines['subtotal'];
                $order->items()->delete();
                $order->items()->createMany($lines['items']);
                $data['subtotal'] = $subtotal;
            }

            $discount = $data['discount_amount'] ?? $order->discount_amount;
            $data['total'] = max(0, $subtotal - $discount);

            $this->orders->update($order, $data);

            return $order->fresh(['items', 'customer']);
        });
    }

    public function delete(Order $order): bool
    {
        return $this->orders->delete($order);
    }

    public function confirm(Order $order): Order
    {
        $this->assertStatus($order, OrderStatus::Pending);
        $order->update(['status' => OrderStatus::Confirmed]);

        return $order;
    }

    public function startProduction(Order $order, ?string $assignedUserId, ?User $creator = null): Order
    {
        $this->assertStatus($order, OrderStatus::Confirmed);

        $order->update(['status' => OrderStatus::InProduction]);

        $order->editingTask()->create([
            'tenant_id' => $order->tenant_id,
            'assigned_user_id' => $assignedUserId,
            'status' => EditingStatus::Pending,
            'created_by' => $creator?->id,
        ]);

        return $order->fresh('editingTask');
    }

    public function readyForDelivery(Order $order): Order
    {
        $this->assertStatus($order, OrderStatus::InProduction);

        $task = $order->editingTask;

        if (! $task || $task->status !== EditingStatus::Completed) {
            throw new ApiException(422, 'The editing task must be completed before this order can be marked ready for delivery.', 'EDITING_TASK_NOT_COMPLETE');
        }

        $order->update(['status' => OrderStatus::ReadyForDelivery]);

        return $order;
    }

    public function deliver(Order $order): Order
    {
        $this->assertStatus($order, OrderStatus::ReadyForDelivery);
        $order->update(['status' => OrderStatus::Delivered]);

        return $order;
    }

    public function cancel(Order $order, string $reason): Order
    {
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            throw new ApiException(422, 'This order can no longer be cancelled.', 'ORDER_CANNOT_BE_CANCELLED');
        }

        $order->update(['status' => OrderStatus::Cancelled, 'cancelled_reason' => $reason]);

        return $order;
    }

    /**
     * A Pending booking can still be rescheduled or cancelled before the
     * customer commits — creating a project (and its billing/production
     * work) against one risks orphaned orders tied to a session that never
     * happens. Cancelled/No Show bookings are excluded for the same reason,
     * just after the fact instead of before. Confirmed, In Progress, and
     * Completed are all fine — the booking is (or was) actually happening.
     */
    protected function assertBookingIsConfirmable(Booking $booking): void
    {
        if (in_array($booking->status, [BookingStatus::Pending, BookingStatus::Cancelled, BookingStatus::NoShow], true)) {
            throw new ApiException(
                422,
                "This booking is \"{$booking->status->label()}\" — it must be confirmed before a project can be created for it.",
                'BOOKING_NOT_CONFIRMED',
                ['status' => $booking->status->label()]
            );
        }
    }

    protected function assertStatus(Order $order, OrderStatus $expected): void
    {
        if ($order->status !== $expected) {
            throw new ApiException(422, "This action requires the order to be \"{$expected->label()}\" (currently \"{$order->status->label()}\").", 'ORDER_INVALID_STATUS_TRANSITION', ['expected' => $expected->label(), 'current' => $order->status->label()]);
        }
    }

    /**
     * Resolves each requested line into a name/unit_price snapshot — from
     * the Service/Add-on/Package catalog if a service_id/addon_id/
     * package_id was given, or taken as-is for a custom (non-catalog)
     * line item. A package's price is its live computed final_price
     * (override, or component total minus discount) at the moment the
     * line is created — never recalculated afterwards.
     */
    protected function resolveLines(array $items): array
    {
        $subtotal = 0;
        $lines = [];

        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if (! empty($item['package_id'])) {
                $package = Package::query()->findOrFail($item['package_id']);
                $name = $package->name;
                $unitPrice = $package->final_price;
            } elseif (! empty($item['service_id'])) {
                $service = Service::query()->findOrFail($item['service_id']);
                $name = $service->name;
                $unitPrice = (float) $service->price;
            } elseif (! empty($item['addon_id'])) {
                /** @var ServiceAddOn $addon */
                $addon = ServiceAddOn::query()->findOrFail($item['addon_id']);
                $name = $addon->name;
                $unitPrice = (float) $addon->price;
            } else {
                $name = $item['name'];
                $unitPrice = (float) $item['unit_price'];
            }

            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'service_id' => $item['service_id'] ?? null,
                'addon_id' => $item['addon_id'] ?? null,
                'package_id' => $item['package_id'] ?? null,
                'name' => $name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        return ['items' => $lines, 'subtotal' => round($subtotal, 2)];
    }
}
