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
            // Sanitized in the controller (in_array(...) ? ... : 'text'),
            // same convention as InvoiceController::sendTelegram() — not
            // worth a Rule::in() here since an unrecognized value just
            // falls back to the default rather than failing validation.
            'format' => ['nullable', 'string'],
        ];
    }
}
