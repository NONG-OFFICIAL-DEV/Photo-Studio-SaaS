<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.update');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('expense_categories', 'name')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($this->route('category')?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
