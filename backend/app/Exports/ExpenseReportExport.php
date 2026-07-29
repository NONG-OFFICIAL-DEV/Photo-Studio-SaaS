<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExpenseReportExport implements FromArray, WithHeadings
{
    public function __construct(protected array $byCategory)
    {
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['category'],
            $row['amount'],
        ], $this->byCategory);
    }

    public function headings(): array
    {
        return ['Category', 'Amount'];
    }
}
