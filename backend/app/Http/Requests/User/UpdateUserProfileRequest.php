<?php

namespace App\Http\Requests\User;

use App\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.update') && $this->user()->tenant_id === $this->route('user')?->tenant_id;
    }

    public function rules(): array
    {
        // Same allow-list StoreUserRequest uses — Owner is never an
        // assignable role via any endpoint, not just excluded here.
        $assignableRoles = collect(TenantRole::cases())
            ->reject(fn (TenantRole $role) => $role === TenantRole::Owner)
            ->map(fn (TenantRole $role) => $role->value)
            ->all();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')?->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role' => ['sometimes', Rule::in($assignableRoles)],
        ];
    }
}
