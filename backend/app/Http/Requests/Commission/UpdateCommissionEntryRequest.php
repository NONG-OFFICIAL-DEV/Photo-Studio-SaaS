<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommissionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('commissionEntry'));
    }

    public function rules(): array
    {
        return [
            'order_id' => [
                'nullable', 'uuid',
                Rule::exists('orders', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'earned_date' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
