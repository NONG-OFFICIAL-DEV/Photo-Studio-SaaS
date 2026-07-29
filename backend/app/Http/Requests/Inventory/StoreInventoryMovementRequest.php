<?php

namespace App\Http\Requests\Inventory;

use App\Enums\MovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjustStock', $this->route('inventoryItem'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MovementType::class)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'moved_at' => ['nullable', 'date'],
        ];
    }
}
