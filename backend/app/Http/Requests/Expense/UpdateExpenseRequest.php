<?php

namespace App\Http\Requests\Expense;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('expense'));
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable', 'uuid',
                Rule::exists('expense_categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'branch_id' => [
                'nullable', 'uuid',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'expense_date' => ['sometimes', 'required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['sometimes', 'required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
