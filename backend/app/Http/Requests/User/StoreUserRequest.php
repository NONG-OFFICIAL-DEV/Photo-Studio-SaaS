<?php

namespace App\Http\Requests\User;

use App\Enums\PayType;
use App\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }

    public function rules(): array
    {
        $assignableRoles = collect(TenantRole::cases())
            ->reject(fn (TenantRole $role) => $role === TenantRole::Owner)
            ->map(fn (TenantRole $role) => $role->value)
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            // Absent/null means "send an invite instead" — see
            // UserController::store().
            'password' => ['nullable', 'string', 'min:8'],
            'branch_id' => [
                'nullable', 'uuid',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'role' => ['required', Rule::in($assignableRoles)],
            'pay_type' => ['nullable', Rule::enum(PayType::class)],
            'base_pay' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
