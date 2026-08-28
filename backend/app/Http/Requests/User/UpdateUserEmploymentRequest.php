<?php

namespace App\Http\Requests\User;

use App\Enums\PayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Scoped to employment fields only (Phase 10 — Employee Management).
 * Full user management (invite/deactivate/roles UI) is still a later
 * phase — see App\Http\Controllers\Api\V1\UserController.
 */
class UpdateUserEmploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.update') && $this->user()->tenant_id === $this->route('user')?->tenant_id;
    }

    public function rules(): array
    {
        return [
            'pay_type' => ['sometimes', 'required', Rule::enum(PayType::class)],
            'base_pay' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Staff branch reassignment — create-time-only until now (see
            // StoreUserRequest); lives here rather than the profile-update
            // request since it affects commission/payroll attribution.
            'branch_id' => [
                'nullable', 'uuid',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }
}
