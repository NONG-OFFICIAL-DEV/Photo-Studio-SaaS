<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class BlacklistCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('blacklist', $this->route('customer'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
