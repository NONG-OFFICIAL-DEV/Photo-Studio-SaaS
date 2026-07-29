<?php

namespace App\Services;

use App\Enums\EditingStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceAddOn;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderService extends BaseService
{
    public function __construct(protected OrderRepositoryInterface $orders)
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

        return DB::transaction(function () use ($data, $items, $creator) {
            $lines = $this->resolveLines($items);

            /** @var Order $order */
            $order = $this->orders->create([
                ...$data,
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
                    throw new HttpException(422, 'Line items can no longer be changed once an order has entered production.');
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
            throw new HttpException(422, 'The editing task must be completed before this order can be marked ready for delivery.');
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
            throw new HttpException(422, 'This order can no longer be cancelled.');
        }

        $order->update(['status' => OrderStatus::Cancelled, 'cancelled_reason' => $reason]);

        return $order;
    }

    protected function assertStatus(Order $order, OrderStatus $expected): void
    {
        if ($order->status !== $expected) {
            throw new HttpException(422, "This action requires the order to be \"{$expected->label()}\" (currently \"{$order->status->label()}\").");
        }
    }

    /**
     * Resolves each requested line into a name/unit_price snapshot — from
     * the Service/Add-on catalog if a service_id/addon_id was given, or
     * taken as-is for a custom (non-catalog) line item.
     */
    protected function resolveLines(array $items): array
    {
        $subtotal = 0;
        $lines = [];

        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if (! empty($item['service_id'])) {
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
                'name' => $name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        return ['items' => $lines, 'subtotal' => round($subtotal, 2)];
    }
}
