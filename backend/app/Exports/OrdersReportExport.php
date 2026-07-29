<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrdersReportExport implements FromArray, WithHeadings
{
    public function __construct(protected array $byStatus)
    {
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['label'],
            $row['count'],
            $row['value'],
        ], $this->byStatus);
    }

    public function headings(): array
    {
        return ['Status', 'Count', 'Value'];
    }
}
