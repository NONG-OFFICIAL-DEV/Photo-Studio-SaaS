<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.create');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('expense_categories', 'name')->where('tenant_id', $this->user()->tenant_id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
