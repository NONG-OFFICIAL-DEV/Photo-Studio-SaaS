<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenant.billing.manage');
    }

    public function rules(): array
    {
        return [
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'yearly'])],
        ];
    }
}
