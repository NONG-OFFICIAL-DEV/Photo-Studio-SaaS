<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gated on albums.update, not customers.update — this is specifically the
 * "deliver photos to a customer over Telegram" action (Photographer/Editor
 * roles have albums.update but not customers.update, and are exactly who
 * should be allowed to do this).
 */
class SendTelegramFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('albums.update');
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:20480'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
