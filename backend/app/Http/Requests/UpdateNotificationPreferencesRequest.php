<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mail' => ['required', 'boolean'],
            'system' => ['required', 'boolean'],
            'telegram' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('telegram') && ! $this->user()->hasTelegramLinked()) {
                $validator->errors()->add('telegram', 'Connect your Telegram account before enabling this channel.');
            }
        });
    }
}
