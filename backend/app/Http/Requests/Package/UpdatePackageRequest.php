<?php

namespace App\Http\Requests\Package;

use App\Enums\DiscountType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('package'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'override_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'components' => ['sometimes', 'array', 'min:1'],
            'components.*.service_id' => [
                'nullable', 'uuid',
                Rule::exists('services', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'components.*.addon_id' => [
                'nullable', 'uuid',
                Rule::exists('service_addons', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'components.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'components.*.is_optional' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('discount_type') === DiscountType::Percent->value && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'A percent discount cannot exceed 100.');
            }

            foreach ($this->input('components', []) as $index => $component) {
                $hasService = ! empty($component['service_id']);
                $hasAddon = ! empty($component['addon_id']);

                if ($hasService === $hasAddon) {
                    $validator->errors()->add(
                        "components.{$index}",
                        'Each component needs exactly one of service_id or addon_id.'
                    );
                }
            }
        });
    }
}
