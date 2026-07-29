<?php

namespace App\Http\Requests\Album;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('album'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'customer_id' => [
                'nullable', 'uuid',
                Rule::exists('customers', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'order_id' => [
                'nullable', 'uuid',
                Rule::exists('orders', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'expected_photo_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
