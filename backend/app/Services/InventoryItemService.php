<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\ApiException;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Repositories\Contracts\InventoryItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventoryItemService extends BaseService
{
    public function __construct(protected InventoryItemRepositoryInterface $items)
    {
        parent::__construct($items);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->items->paginateServer($filters);
    }

    public function create(array $data, ?User $creator = null): InventoryItem
    {
        return $this->items->create([
            ...$data,
            'created_by' => $creator?->id,
        ]);
    }

    public function update(InventoryItem $item, array $data): InventoryItem
    {
        return $this->items->update($item, $data);
    }

    public function delete(InventoryItem $item): bool
    {
        return $this->items->delete($item);
    }

    /**
     * Records a stock movement, then recomputes quantity_on_hand from the
     * full movement history (rather than incrementing/decrementing it
     * directly) so it can never drift from the audit trail.
     */
    public function recordMovement(InventoryItem $item, array $data, ?User $recorder = null): InventoryItem
    {
        $type = MovementType::from($data['type']);
        $quantity = round((float) $data['quantity'], 2);

        if ($type === MovementType::StockOut && $quantity > (float) $item->quantity_on_hand) {
            throw new ApiException(422, "Cannot remove {$quantity} {$item->unit} — only {$item->quantity_on_hand} {$item->unit} in stock.", 'INSUFFICIENT_STOCK', ['quantity' => $quantity, 'unit' => $item->unit, 'available' => $item->quantity_on_hand]);
        }

        return DB::transaction(function () use ($item, $data, $type, $quantity, $recorder) {
            $item->movements()->create([
                'type' => $type,
                'quantity' => $quantity,
                'reason' => $data['reason'] ?? null,
                'moved_at' => $data['moved_at'] ?? now()->toDateString(),
                'recorded_by' => $recorder?->id,
            ]);

            $this->recalculateQuantity($item);

            return $item->fresh('movements');
        });
    }

    public function deleteMovement(InventoryItem $item, InventoryMovement $movement): InventoryItem
    {
        if ($movement->inventory_item_id !== $item->id) {
            throw new ApiException(404, 'Movement not found for this item.', 'MOVEMENT_NOT_FOUND');
        }

        return DB::transaction(function () use ($item, $movement) {
            $movement->delete();
            $this->recalculateQuantity($item);

            return $item->fresh('movements');
        });
    }

    protected function recalculateQuantity(InventoryItem $item): void
    {
        $stockIn = (float) $item->movements()->where('type', MovementType::StockIn->value)->sum('quantity');
        $stockOut = (float) $item->movements()->where('type', MovementType::StockOut->value)->sum('quantity');

        $item->update(['quantity_on_hand' => round($stockIn - $stockOut, 2)]);
    }
}
