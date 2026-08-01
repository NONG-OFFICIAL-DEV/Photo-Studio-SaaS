<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ConnectTelegramBotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenant.settings.manage');
    }

    public function rules(): array
    {
        return [
            'bot_token' => ['required', 'string', 'max:255'],
        ];
    }
}
