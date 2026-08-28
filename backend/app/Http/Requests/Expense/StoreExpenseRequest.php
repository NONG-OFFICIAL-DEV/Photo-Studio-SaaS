<?php

namespace App\Http\Requests\Expense;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
