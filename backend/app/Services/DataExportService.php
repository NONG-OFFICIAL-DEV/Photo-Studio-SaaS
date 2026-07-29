<?php

namespace App\Services;

use App\Exports\CustomersExport;
use App\Exports\ExpensesExport;
use App\Exports\InvoicesExport;
use App\Exports\OrdersExport;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriterType;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;

/**
 * The "backup" feature from Settings — a full data export as a single zip
 * of CSVs (Customers/Orders/Invoices/Expenses), each generated in-memory
 * via Excel::raw() and written straight into the archive, so no per-file
 * temp CSVs need to be created or cleaned up — only the final zip.
 */
class DataExportService
{
    public function buildZip(): string
    {
        $path = storage_path('app/'.Str::uuid().'-export.zip');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);

        $zip->addFromString('customers.csv', Excel::raw(new CustomersExport([]), ExcelWriterType::CSV));
        $zip->addFromString('orders.csv', Excel::raw(new OrdersExport, ExcelWriterType::CSV));
        $zip->addFromString('invoices.csv', Excel::raw(new InvoicesExport, ExcelWriterType::CSV));
        $zip->addFromString('expenses.csv', Excel::raw(new ExpensesExport, ExcelWriterType::CSV));

        $zip->close();

        return $path;
    }
}
