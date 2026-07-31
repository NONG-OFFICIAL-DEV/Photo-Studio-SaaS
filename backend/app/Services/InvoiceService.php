<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\ApiException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceAddOn;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

class InvoiceService extends BaseService
{
    public function __construct(protected InvoiceRepositoryInterface $invoices, protected TenantContext $tenantContext)
    {
        parent::__construct($invoices);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->invoices->paginateServer($filters);
    }

    /**
     * Creates an invoice either from an explicit `items` array, or — when
     * `order_id` is given with no items — by snapshotting the linked
     * Order's own line items, the same way Order snapshots the Service
     * catalog. Either way, totals are (re)computed from the resolved lines.
     */
    public function create(array $data, ?User $creator = null): Invoice
    {
        $items = $data['items'] ?? null;
        unset($data['items']);

        $tenant = $this->tenantContext->get();

        return DB::transaction(function () use ($data, $items, $creator, $tenant) {
            if ($items === null && ! empty($data['order_id'])) {
                $order = Order::query()->with('items')->findOrFail($data['order_id']);
                $data['customer_id'] = $data['customer_id'] ?? $order->customer_id;
                $items = $order->items->map(fn ($item) => [
                    'service_id' => $item->service_id,
                    'addon_id' => $item->addon_id,
                    'name' => $item->name,
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => $item->quantity,
                ])->all();
            }

            $lines = $this->resolveLines($items ?? []);
            $discount = (float) ($data['discount_amount'] ?? 0);
            $taxRate = array_key_exists('tax_rate', $data) && $data['tax_rate'] !== null
                ? (float) $data['tax_rate']
                : (float) ($tenant?->setting('default_tax_rate') ?? 0);
            $totals = $this->computeTotals($lines['subtotal'], $discount, $taxRate);

            $issueDate = $data['issue_date'] ?? now()->toDateString();
            $dueDate = $data['due_date']
                ?? Carbon::parse($issueDate)->addDays((int) ($tenant?->setting('default_due_days') ?? 14))->toDateString();

            /** @var Invoice $invoice */
            $invoice = $this->invoices->create([
                ...$data,
                'invoice_number' => $this->nextInvoiceNumber($tenant),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'discount_amount' => $discount,
                'tax_rate' => $taxRate,
                'subtotal' => $lines['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'created_by' => $creator?->id,
            ]);

            $invoice->items()->createMany($lines['items']);

            return $invoice->load('items', 'customer', 'order');
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status === InvoiceStatus::Void) {
            throw new ApiException(422, 'A voided invoice can no longer be edited.', 'INVOICE_VOIDED_LOCKED');
        }

        $items = $data['items'] ?? null;
        unset($data['items']);

        $changingTotals = $items !== null
            || array_key_exists('discount_amount', $data)
            || array_key_exists('tax_rate', $data);

        if ($changingTotals && $invoice->status !== InvoiceStatus::Draft) {
            throw new ApiException(422, 'Line items, discount, and tax can only be changed while the invoice is still a draft.', 'INVOICE_NOT_DRAFT_LOCKED');
        }

        return DB::transaction(function () use ($invoice, $data, $items) {
            $subtotal = (float) $invoice->subtotal;

            if ($items !== null) {
                $lines = $this->resolveLines($items);
                $subtotal = $lines['subtotal'];
                $invoice->items()->delete();
                $invoice->items()->createMany($lines['items']);
            }

            $discount = (float) ($data['discount_amount'] ?? $invoice->discount_amount);
            $taxRate = (float) ($data['tax_rate'] ?? $invoice->tax_rate);
            $totals = $this->computeTotals($subtotal, $discount, $taxRate);

            $this->invoices->update($invoice, [
                ...$data,
                'subtotal' => $subtotal,
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
            ]);

            return $invoice->fresh(['items', 'customer', 'order']);
        });
    }

    public function delete(Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new ApiException(422, 'Only draft invoices can be deleted — void it instead.', 'INVOICE_NOT_DRAFT_DELETE');
        }

        return $this->invoices->delete($invoice);
    }

    public function send(Invoice $invoice): Invoice
    {
        $this->assertStatus($invoice, InvoiceStatus::Draft);
        $invoice->update(['status' => InvoiceStatus::Sent]);

        return $invoice;
    }

    /**
     * Renders the invoice as a PDF — shared by the authenticated download
     * endpoint and the unauthenticated signed-link endpoint (customers have
     * no account, so that route can't require auth:api/tenant).
     *
     * Rendered through headless Chrome (Browsershot) rather than dompdf:
     * dompdf draws text glyph-by-glyph straight from the font's cmap with
     * no OpenType shaping engine, so Khmer's subscript consonant clusters
     * (the invisible "coeng" joiner + following consonant) never get
     * substituted into their stacked form — they render as a broken
     * joiner glyph next to a full-size consonant. A real browser engine
     * shapes them correctly.
     */
    public function renderPdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'customer', 'order', 'tenant']);

        $html = View::make('pdf.invoice', [
            'invoice' => $invoice,
            'logoDataUri' => $this->logoDataUri($invoice->tenant),
            'qrPaymentDataUri' => $this->qrPaymentDataUri($invoice->tenant),
            'khmerFontRegularDataUri' => $this->fontDataUri('NotoSansKhmer-Regular.ttf'),
            'khmerFontBoldDataUri' => $this->fontDataUri('NotoSansKhmer-Bold.ttf'),
        ])->render();

        return Browsershot::html($html)
            ->setNodeBinary(config('browsershot.node_binary'))
            ->setNodeModulePath(config('browsershot.node_modules_path'))
            ->setChromePath(config('browsershot.chrome_path'))
            ->noSandbox()
            ->format('A4')
            ->showBackground()
            ->pdf();
    }

    /**
     * Embedded as a base64 data URI rather than a plain <img src="{{ $url }}">
     * so the tenant's logo loads the same way regardless of PDF renderer —
     * dompdf has remote fetching disabled by default, and headless Chrome
     * rendering an HTML string (no base URL) can't resolve a relative or
     * even same-origin URL back to this app either. Reading straight off
     * disk sidesteps both, and is a network round-trip fewer either way.
     * Falls back to no logo (rather than a broken image or a failed PDF)
     * if the tenant hasn't uploaded one, or the stored file is missing.
     */
    protected function logoDataUri(Tenant $tenant): ?string
    {
        if (! $tenant->logo_path || ! Storage::disk('public')->exists($tenant->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($tenant->logo_path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode(Storage::disk('public')->get($tenant->logo_path));
    }

    /**
     * Same reasoning and fallback behavior as logoDataUri() — a tenant's
     * scan-to-pay QR code (e.g. KHQR), shown near the invoice footer.
     */
    protected function qrPaymentDataUri(Tenant $tenant): ?string
    {
        if (! $tenant->qr_payment_path || ! Storage::disk('public')->exists($tenant->qr_payment_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($tenant->qr_payment_path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode(Storage::disk('public')->get($tenant->qr_payment_path));
    }

    /**
     * Same base64-data-URI reasoning as logoDataUri() — headless Chrome
     * rendering a bare HTML string has no base URL to resolve a local
     * file:// font path against, so the @font-face src has to be
     * self-contained.
     */
    protected function fontDataUri(string $filename): string
    {
        return 'data:font/ttf;base64,'.base64_encode(file_get_contents(resource_path("fonts/{$filename}")));
    }

    public function void(Invoice $invoice, string $reason): Invoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Void], true)) {
            throw new ApiException(422, 'A paid or already-voided invoice cannot be voided.', 'INVOICE_ALREADY_VOIDED_OR_PAID');
        }

        $invoice->update(['status' => InvoiceStatus::Void, 'voided_reason' => $reason]);

        return $invoice;
    }

    public function recordPayment(Invoice $invoice, array $data, ?User $recorder = null): Invoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)) {
            throw new ApiException(422, 'Payments can only be recorded against a sent invoice.', 'INVOICE_NOT_SENT');
        }

        return DB::transaction(function () use ($invoice, $data, $recorder) {
            $amount = round((float) $data['amount'], 2);
            $remaining = round((float) $invoice->total - (float) $invoice->amount_paid, 2);

            if ($amount > $remaining) {
                throw new ApiException(422, "Payment of {$amount} exceeds the remaining balance of {$remaining}.", 'PAYMENT_EXCEEDS_BALANCE', ['amount' => $amount, 'remaining' => $remaining]);
            }

            $invoice->payments()->create([
                'amount' => $amount,
                'method' => $data['method'],
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $recorder?->id,
            ]);

            $this->recalculateStatus($invoice);

            return $invoice->fresh(['items', 'customer', 'order', 'payments']);
        });
    }

    public function deletePayment(Invoice $invoice, Payment $payment): Invoice
    {
        if ($payment->invoice_id !== $invoice->id) {
            throw new ApiException(404, 'Payment not found for this invoice.', 'PAYMENT_NOT_FOUND');
        }

        return DB::transaction(function () use ($invoice, $payment) {
            $payment->delete();
            $this->recalculateStatus($invoice);

            return $invoice->fresh(['items', 'customer', 'order', 'payments']);
        });
    }

    /**
     * Bulk-transitions every Sent/PartiallyPaid invoice past its due date
     * into Overdue. Run tenant-agnostically (no TenantContext set) so it
     * sweeps every tenant in one pass — intended for the daily scheduler.
     */
    public function markOverdue(): int
    {
        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::PartiallyPaid->value])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => InvoiceStatus::Overdue->value]);
    }

    protected function recalculateStatus(Invoice $invoice): void
    {
        $amountPaid = round((float) $invoice->payments()->sum('amount'), 2);

        if ($amountPaid <= 0) {
            $status = $invoice->due_date && $invoice->due_date->isPast() ? InvoiceStatus::Overdue : InvoiceStatus::Sent;
        } elseif ($amountPaid >= (float) $invoice->total) {
            $status = InvoiceStatus::Paid;
        } else {
            $status = InvoiceStatus::PartiallyPaid;
        }

        $invoice->update(['amount_paid' => $amountPaid, 'status' => $status]);
    }

    protected function assertStatus(Invoice $invoice, InvoiceStatus $expected): void
    {
        if ($invoice->status !== $expected) {
            throw new ApiException(422, "This action requires the invoice to be \"{$expected->label()}\" (currently \"{$invoice->status->label()}\").", 'INVOICE_INVALID_STATUS_TRANSITION', ['expected' => $expected->label(), 'current' => $invoice->status->label()]);
        }
    }

    protected function computeTotals(float $subtotal, float $discount, float $taxRate): array
    {
        $taxable = max(0, round($subtotal - $discount, 2));
        $taxAmount = round($taxable * ($taxRate / 100), 2);

        return ['tax_amount' => $taxAmount, 'total' => round($taxable + $taxAmount, 2)];
    }

    /**
     * Resolves each requested line into a name/unit_price snapshot — from
     * the Service/Add-on/Package catalog if a service_id/addon_id/
     * package_id was given, or taken as-is for a custom (non-catalog)
     * line item. Mirrors OrderService::resolveLines() for the
     * invoice_items table.
     */
    protected function resolveLines(array $items): array
    {
        $subtotal = 0;
        $lines = [];

        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if (! empty($item['package_id'])) {
                $package = Package::query()->findOrFail($item['package_id']);
                $name = $package->name;
                $unitPrice = $package->final_price;
            } elseif (! empty($item['service_id'])) {
                $service = Service::query()->findOrFail($item['service_id']);
                $name = $service->name;
                $unitPrice = (float) $service->price;
            } elseif (! empty($item['addon_id'])) {
                /** @var ServiceAddOn $addon */
                $addon = ServiceAddOn::query()->findOrFail($item['addon_id']);
                $name = $addon->name;
                $unitPrice = (float) $addon->price;
            } else {
                $name = $item['name'];
                $unitPrice = (float) $item['unit_price'];
            }

            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'service_id' => $item['service_id'] ?? null,
                'addon_id' => $item['addon_id'] ?? null,
                'package_id' => $item['package_id'] ?? null,
                'name' => $name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        return ['items' => $lines, 'subtotal' => round($subtotal, 2)];
    }

    protected function nextInvoiceNumber(?Tenant $tenant = null): string
    {
        $count = Invoice::withTrashed()->count();
        $prefix = $tenant?->setting('invoice_prefix') ?? Tenant::SETTINGS_DEFAULTS['invoice_prefix'];

        return $prefix.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
