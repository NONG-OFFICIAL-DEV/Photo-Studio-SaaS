<?php

namespace App\Exports;

use App\Models\Expense;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ExpensesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Expense::query()->with('category')->latest('expense_date')->get();
    }

    public function headings(): array
    {
        return ['Category', 'Amount', 'Expense Date', 'Vendor', 'Payment Method'];
    }

    public function map($expense): array
    {
        return [
            $expense->category?->name,
            $expense->amount,
            $expense->expense_date?->format('Y-m-d'),
            $expense->vendor,
            $expense->payment_method?->value,
        ];
    }
}
