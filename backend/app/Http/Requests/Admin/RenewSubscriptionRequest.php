<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the `super-admin` route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'yearly'])],
        ];
    }
}
