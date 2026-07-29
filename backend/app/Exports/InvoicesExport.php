<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoicesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Invoice::query()->with('customer')->latest()->get();
    }

    public function headings(): array
    {
        return ['Invoice #', 'Customer', 'Status', 'Issue Date', 'Due Date', 'Total', 'Amount Paid'];
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->customer?->name,
            $invoice->status?->value,
            $invoice->issue_date?->format('Y-m-d'),
            $invoice->due_date?->format('Y-m-d'),
            $invoice->total,
            $invoice->amount_paid,
        ];
    }
}
