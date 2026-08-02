<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Requests\Invoice\VoidInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\TelegramService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\HeaderUtils;
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
     * The "click one button, customer gets the invoice" flow. `format`
     * picks the attachment (falls back to 'pdf' on anything unrecognized,
     * same defensive-default style as ReportController's export `format`
     * query param): 'pdf' downloads/renders the same document
     * downloadPdf() serves, 'image' renders the same layout as a single
     * PNG, 'text' sends no attachment at all — just the formatted message.
     *
     * Whichever format is chosen, if the tenant has a payment QR uploaded,
     * a SECOND message follows with that QR as its own plain photo — a
     * customer can crop a QR out of a PDF, but "save this photo, scan it"
     * is a much lower-friction ask, so this doesn't piggyback on whichever
     * format happened to be picked. Never touches this app's own storage
     * either way.
     */
    public function sendTelegram(Request $request, Invoice $invoice, TelegramService $telegram): JsonResponse
    {
        $this->authorize('send', $invoice);

        $invoice->loadMissing('customer');
        $tenant = $invoice->tenant;

        if (! $tenant->telegramConnected()) {
            return $this->error('Connect a Telegram bot in Settings first.', 422, [], 'TELEGRAM_NOT_CONFIGURED');
        }

        if (! $invoice->customer?->telegram_chat_id) {
            return $this->error('This customer has not connected Telegram yet.', 422, [], 'TELEGRAM_CUSTOMER_NOT_LINKED');
        }

        $format = $request->input('format', 'pdf');
        $format = in_array($format, ['pdf', 'image', 'text'], true) ? $format : 'pdf';
        $chatId = $invoice->customer->telegram_chat_id;
        $token = $tenant->telegram_bot_token;
        $caption = $this->invoices->invoiceSummaryText($invoice);

        $result = match ($format) {
            'image' => $telegram->sendPhoto($token, $chatId, $this->invoices->renderImage($invoice), $caption),
            'text' => $telegram->sendMessage($token, $chatId, $caption),
            default => $telegram->sendDocument($token, $chatId, $this->invoices->renderPdf($invoice), "{$invoice->invoice_number}.pdf", $caption),
        };

        if (! ($result['ok'] ?? false)) {
            return $this->error('Failed to send via Telegram.', 502, [], 'TELEGRAM_SEND_FAILED');
        }

        if ($qrImage = $this->invoices->qrPaymentImageBytes($tenant)) {
            $telegram->sendPhoto($token, $chatId, $qrImage, 'Scan to pay via your banking app.', 'payment-qr.png');
        }

        return $this->success(null, 'Invoice sent via Telegram.');
    }

    /**
     * Authenticated staff download — same PDF the signed public link below
     * serves, just gated by the normal view permission instead of a
     * signature, and via Content-Disposition: attachment instead of inline.
     */
    public function downloadPdf(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return $this->pdfResponse($this->invoices->renderPdf($invoice), $invoice->invoice_number, HeaderUtils::DISPOSITION_ATTACHMENT);
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

        return $this->pdfResponse($this->invoices->renderPdf($invoice), $invoice->invoice_number, HeaderUtils::DISPOSITION_INLINE);
    }

    /**
     * Browsershot returns raw PDF bytes rather than dompdf's response
     * wrapper, so download/inline headers are built here instead of via
     * a library helper — HeaderUtils::makeDisposition still handles the
     * RFC 6266 escaping for us.
     */
    protected function pdfResponse(string $pdf, string $filename, string $disposition): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, "{$filename}.pdf"),
        ]);
    }
}
