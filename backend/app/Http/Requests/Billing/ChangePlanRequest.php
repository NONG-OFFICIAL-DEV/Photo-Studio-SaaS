<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenant.billing.manage');
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'yearly'])],
        ];
    }
}
