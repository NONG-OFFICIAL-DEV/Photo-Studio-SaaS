<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Requests\Invoice\VoidInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(protected InvoiceService $invoices)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $paginator = $this->invoices->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage', 'status', 'customer_id', 'order_id',
        ]));

        return $this->success(
            InvoiceResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->create($request->validated(), $request->user());

        return $this->created(new InvoiceResource($invoice), 'Invoice created successfully.');
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load('items', 'customer', 'order', 'payments.recordedBy');

        return $this->success(new InvoiceResource($invoice));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->update($invoice, $request->validated());

        return $this->success(new InvoiceResource($invoice), 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $this->invoices->delete($invoice);

        return $this->noContent('Invoice deleted successfully.');
    }

    public function send(Invoice $invoice): JsonResponse
    {
        $this->authorize('send', $invoice);

        return $this->success(new InvoiceResource($this->invoices->send($invoice)), 'Invoice sent.');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->void($invoice, $request->string('reason')->toString());

        return $this->success(new InvoiceResource($invoice), 'Invoice voided.');
    }

    /**
     * Authenticated staff download — same PDF the signed public link below
     * serves, just gated by the normal view permission instead of a
     * signature, and via Content-Disposition: attachment instead of inline.
     */
    public function downloadPdf(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return $this->invoices->renderPdf($invoice)->download("{$invoice->invoice_number}.pdf");
    }

    /**
     * Generates a time-limited signed link (no login required) staff can
     * paste into Telegram/WhatsApp/SMS — the customer has no account, so
     * this can't be gated by auth:api like every other invoice route.
     * Reusing the same 'signed' pattern as the email-verification link.
     */
    public function shareLink(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $expiresAt = now()->addDays(30);
        $url = URL::temporarySignedRoute('api.v1.invoices.public-pdf', $expiresAt, ['invoice' => $invoice->id]);

        return $this->success(['url' => $url, 'expires_at' => $expiresAt]);
    }

    /**
     * The public counterpart of downloadPdf() — deliberately outside the
     * tenant/auth middleware group (see routes/api/v1.php), so the Invoice
     * is looked up with global scopes bypassed rather than via the usual
     * tenant-scoped route-model binding.
     */
    public function publicPdf(string $invoice): Response
    {
        $invoice = Invoice::withoutGlobalScopes()->findOrFail($invoice);

        return $this->invoices->renderPdf($invoice)->stream("{$invoice->invoice_number}.pdf");
    }
}
