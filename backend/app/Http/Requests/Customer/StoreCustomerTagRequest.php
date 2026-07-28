<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Customer::class);
    }

    public function rules(): array
    {
        if (app()->environment('testing')) {
            fwrite(STDERR, 'RULES user_id='.$this->user()?->id.' tenant_id='.$this->user()?->tenant_id."\n");
        }

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('customer_tags', 'name')->where('tenant_id', $this->user()->tenant_id),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
