<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'sometimes', 'required', 'uuid',
                Rule::exists('customers', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.service_id' => [
                'nullable', 'uuid',
                Rule::exists('services', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'items.*.addon_id' => [
                'nullable', 'uuid',
                Rule::exists('service_addons', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('items', []) as $index => $item) {
                $hasCatalogRef = ! empty($item['service_id']) || ! empty($item['addon_id']);
                $hasCustomLine = ! empty($item['name']) && isset($item['unit_price']);

                if (! $hasCatalogRef && ! $hasCustomLine) {
                    $validator->errors()->add(
                        "items.{$index}",
                        'Each item needs either a service_id/addon_id or a custom name and unit_price.'
                    );
                }
            }
        });
    }
}
