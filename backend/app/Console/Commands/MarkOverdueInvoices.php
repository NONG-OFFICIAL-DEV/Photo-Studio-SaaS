<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Transition every Sent/Partially Paid invoice past its due date into Overdue, across all tenants';

    public function handle(InvoiceService $invoices): int
    {
        $count = $invoices->markOverdue();

        $this->info("Marked {$count} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
