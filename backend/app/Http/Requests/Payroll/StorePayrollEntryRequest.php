<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PayrollEntry::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
                Rule::unique('payroll_entries')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('period_start', $this->input('period_start'))
                    ->where('period_end', $this->input('period_end')),
            ],
            'period_label' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'base_pay' => ['nullable', 'numeric', 'min:0'],
            'commission_total' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'A payroll entry already exists for this employee and period.',
        ];
    }
}
