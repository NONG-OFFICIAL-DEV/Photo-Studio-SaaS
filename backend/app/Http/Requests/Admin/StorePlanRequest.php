<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the `super-admin` route middleware — no further RBAC
        // check applies here (super admins have no tenant-scoped roles).
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('plans', 'code')],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'price_quarterly' => ['nullable', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_branches' => ['nullable', 'integer', 'min:1'],
            'storage_limit_gb' => ['nullable', 'integer', 'min:0'],
            'monthly_order_limit' => ['nullable', 'integer', 'min:0'],
            'has_watermark_gallery' => ['boolean'],
            'has_online_gallery' => ['boolean'],
            'has_reports' => ['boolean'],
            'has_api_access' => ['boolean'],
            'has_telegram' => ['boolean'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
