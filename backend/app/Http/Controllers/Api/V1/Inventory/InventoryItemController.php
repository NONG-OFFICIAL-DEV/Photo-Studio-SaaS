<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Services\InventoryItemService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    use ApiResponse;

    public function __construct(protected InventoryItemService $items) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InventoryItem::class);

        $paginator = $this->items->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage', 'is_active', 'category', 'low_stock', 'branch_id',
        ]));

        return $this->success(
            InventoryItemResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        $item = $this->items->create($request->validated(), $request->user());

        return $this->created(new InventoryItemResource($item), 'Inventory item created successfully.');
    }

    public function show(InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorize('view', $inventoryItem);

        return $this->success(new InventoryItemResource($inventoryItem->load('movements.recordedBy')));
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem = $this->items->update($inventoryItem, $request->validated());

        return $this->success(new InventoryItemResource($inventoryItem), 'Inventory item updated successfully.');
    }

    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorize('delete', $inventoryItem);

        $this->items->delete($inventoryItem);

        return $this->noContent('Inventory item deleted successfully.');
    }
}
