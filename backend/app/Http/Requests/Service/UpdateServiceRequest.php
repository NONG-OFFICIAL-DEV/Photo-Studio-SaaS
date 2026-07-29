<?php

namespace App\Http\Requests\Service;

use App\Enums\PricingUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('service'));
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable', 'uuid',
                Rule::exists('service_categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'deliverables' => ['nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999.99'],
            'pricing_unit' => ['sometimes', 'required', Rule::in(array_column(PricingUnit::cases(), 'value'))],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
