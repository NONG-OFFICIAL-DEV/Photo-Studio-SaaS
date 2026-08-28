<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlanFeatureValueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanFeatureListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // `key` is intentionally not accepted here — the frontend
            // disables the field on edit (matching Plan.code's own
            // immutable-after-creation convention), since Plan.feature_labels
            // rows reference it by string; renaming it out from under
            // existing plan data would silently orphan their values.
            'label_en' => ['sometimes', 'required', 'string', 'max:255'],
            'label_km' => ['nullable', 'string', 'max:255'],
            'value_type' => ['sometimes', 'required', Rule::enum(PlanFeatureValueType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
