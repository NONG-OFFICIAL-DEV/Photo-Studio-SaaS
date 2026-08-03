<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Requires the super admin to type the tenant's exact current name before
 * this irreversible action is allowed to proceed — the same "type the
 * resource name to confirm" pattern used by other platforms for
 * destructive actions. Checked server-side (not just in the frontend
 * dialog) since a client-only check is trivially bypassable via a direct
 * API call.
 */
class DeleteTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_name' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tenant = $this->route('tenant');

            if ($tenant && $this->input('confirm_name') !== $tenant->name) {
                $validator->errors()->add('confirm_name', 'The typed name does not match this tenant\'s name.');
            }
        });
    }
}
