<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', InventoryItem::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable', 'string', 'max:100',
                Rule::unique('inventory_items', 'sku')->where('tenant_id', $this->user()->tenant_id),
            ],
            'unit' => ['required', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'reorder_threshold' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            // Only meaningful on create — quantity is otherwise always
            // driven by a movement, never set directly (see
            // InventoryItemService::recalculateQuantity()). This becomes
            // the item's first stock_in movement rather than a raw column
            // write, so the audit trail still fully explains the balance.
            'initial_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
