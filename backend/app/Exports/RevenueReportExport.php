<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RevenueReportExport implements FromArray, WithHeadings
{
    public function __construct(protected array $breakdown)
    {
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['period'],
            $row['invoiced'],
            $row['collected'],
        ], $this->breakdown);
    }

    public function headings(): array
    {
        return ['Period', 'Invoiced', 'Collected'];
    }
}
