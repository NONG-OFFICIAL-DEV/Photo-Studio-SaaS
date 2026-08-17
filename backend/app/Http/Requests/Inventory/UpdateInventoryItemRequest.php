<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('inventoryItem'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'branch_id' => [
                'sometimes', 'nullable', 'uuid',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'sku' => [
                'nullable', 'string', 'max:100',
                Rule::unique('inventory_items', 'sku')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($this->route('inventoryItem')?->id),
            ],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'reorder_threshold' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
