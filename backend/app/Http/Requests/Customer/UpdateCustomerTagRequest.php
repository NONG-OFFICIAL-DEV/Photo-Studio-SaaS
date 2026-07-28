<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tags don't have their own policy — they're managed under the
        // same customers.* permission set (route-model binding already
        // guarantees {tag} belongs to the current tenant via TenantScope).
        return $this->user()->can('customers.update');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('customer_tags', 'name')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($this->route('tag')?->id),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
