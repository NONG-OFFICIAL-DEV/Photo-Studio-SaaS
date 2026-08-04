<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenant.billing.manage');
    }

    public function rules(): array
    {
        return [
            'claimed_amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
