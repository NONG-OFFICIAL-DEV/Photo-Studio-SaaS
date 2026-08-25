<?php

namespace App\Http\Requests\Auth;

use App\Enums\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoogleRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'studio_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'phone' => ['nullable', 'string', 'max:30'],
            'plan_code' => ['nullable', 'string', 'exists:plans,code'],
            'billing_cycle' => ['nullable', Rule::in(array_column(BillingCycle::cases(), 'value'))],
        ];
    }
}
