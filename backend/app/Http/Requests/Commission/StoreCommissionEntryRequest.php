<?php

namespace App\Http\Requests\Commission;

use App\Models\CommissionEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommissionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CommissionEntry::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'order_id' => [
                'nullable', 'uuid',
                Rule::exists('orders', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'earned_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
