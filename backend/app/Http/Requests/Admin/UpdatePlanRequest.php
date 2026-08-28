<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlanFeatureValueType;
use App\Models\PlanFeatureListing;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('plans', 'code')->ignore($this->route('plan')?->id)],
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
            // See StorePlanRequest — same dynamic-catalog validation applies.
            'feature_labels' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $catalog = PlanFeatureListing::query()->where('is_active', true)->get()->keyBy('key');

            foreach ($this->input('feature_labels', []) as $key => $value) {
                $listing = $catalog->get($key);

                if (! $listing) {
                    $validator->errors()->add("feature_labels.{$key}", "Unknown feature key \"{$key}\".");

                    continue;
                }

                if ($listing->value_type === PlanFeatureValueType::Boolean) {
                    if (! is_bool($value)) {
                        $validator->errors()->add("feature_labels.{$key}", "The \"{$key}\" feature must be true or false.");
                    }
                } elseif (! is_array($value) || empty($value['en'])) {
                    $validator->errors()->add("feature_labels.{$key}.en", "The \"{$key}\" feature's English value is required.");
                }
            }
        });
    }
}
