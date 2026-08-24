<?php

namespace App\Http\Requests\Auth;

use App\Enums\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'studio_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'plan_code' => ['nullable', 'string', 'exists:plans,code'],
            'billing_cycle' => ['nullable', Rule::in(array_column(BillingCycle::cases(), 'value'))],
        ];
    }
}
