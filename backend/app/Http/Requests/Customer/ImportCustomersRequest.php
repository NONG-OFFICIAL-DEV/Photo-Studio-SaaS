<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ImportCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', \App\Models\Customer::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ];
    }
}
