<?php

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;

class SendPackageTelegramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('send', $this->route('package'));
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'string'],
        ];
    }
}
