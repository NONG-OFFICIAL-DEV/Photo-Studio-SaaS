<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\StartProductionRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(protected OrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $paginator = $this->orders->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage',
            'status', 'customer_id', 'booking_id',
        ]));

        return $this->success(
            OrderResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->validated(), $request->user());

        return $this->created(new OrderResource($order), 'Order created successfully.');
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load('items', 'customer', 'editingTask.assignedUser');

        return $this->success(new OrderResource($order));
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->update($order, $request->validated());

        return $this->success(new OrderResource($order), 'Order updated successfully.');
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);

        $this->orders->delete($order);

        return $this->noContent('Order deleted successfully.');
    }

    public function confirm(Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        return $this->success(new OrderResource($this->orders->confirm($order)), 'Order confirmed.');
    }

    public function startProduction(StartProductionRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->startProduction($order, $request->input('assigned_user_id'), $request->user());

        return $this->success(new OrderResource($order->load('editingTask.assignedUser')), 'Order moved into production.');
    }

    public function readyForDelivery(Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        return $this->success(new OrderResource($this->orders->readyForDelivery($order)), 'Order marked ready for delivery.');
    }

    public function deliver(Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        return $this->success(new OrderResource($this->orders->deliver($order)), 'Order delivered.');
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->cancel($order, $request->string('reason')->toString());

        return $this->success(new OrderResource($order), 'Order cancelled.');
    }
}
