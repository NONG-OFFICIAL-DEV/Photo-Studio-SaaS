<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('payrollEntry'));
    }

    public function rules(): array
    {
        return [
            'period_label' => ['sometimes', 'required', 'string', 'max:255'],
            'base_pay' => ['nullable', 'numeric', 'min:0'],
            'commission_total' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
