<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlanFeatureValueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanFeatureListingRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', Rule::unique('plan_feature_listings', 'key')],
            'label_en' => ['required', 'string', 'max:255'],
            'label_km' => ['nullable', 'string', 'max:255'],
            'value_type' => ['required', Rule::enum(PlanFeatureValueType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
