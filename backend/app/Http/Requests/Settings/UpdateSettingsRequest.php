<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenant.settings.manage');
    }

    public function rules(): array
    {
        return [
            // Company
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'timezone' => ['sometimes', 'required', 'string', 'max:100'],

            // Invoicing defaults
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'invoice_footer' => ['nullable', 'string', 'max:2000'],

            // Theme
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
