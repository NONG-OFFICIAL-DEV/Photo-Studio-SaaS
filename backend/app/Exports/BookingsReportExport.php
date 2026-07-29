<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Exports the by-status breakdown only — by-type stays view-only on the
 * Reports page (keeps this to a single flat sheet rather than a
 * multi-sheet workbook).
 */
class BookingsReportExport implements FromArray, WithHeadings
{
    public function __construct(protected array $byStatus)
    {
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['label'],
            $row['count'],
        ], $this->byStatus);
    }

    public function headings(): array
    {
        return ['Status', 'Count'];
    }
}
