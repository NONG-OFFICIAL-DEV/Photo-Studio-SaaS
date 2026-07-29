<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\InventoryItemService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class InventoryMovementController extends Controller
{
    use ApiResponse;

    public function __construct(protected InventoryItemService $items)
    {
    }

    public function store(StoreInventoryMovementRequest $request, InventoryItem $inventoryItem): JsonResponse
    {
        $item = $this->items->recordMovement($inventoryItem, $request->validated(), $request->user());

        return $this->created(new InventoryItemResource($item), 'Stock movement recorded successfully.');
    }

    public function destroy(InventoryItem $inventoryItem, InventoryMovement $movement): JsonResponse
    {
        $this->authorize('adjustStock', $inventoryItem);

        $item = $this->items->deleteMovement($inventoryItem, $movement);

        return $this->success(new InventoryItemResource($item), 'Stock movement removed successfully.');
    }
}
